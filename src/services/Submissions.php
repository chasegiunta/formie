<?php
namespace verbb\formie\services;

use verbb\formie\Formie;
use verbb\formie\controllers\SubmissionsController;
use verbb\formie\elements\Submission;
use verbb\formie\events\SubmissionEvent;
use verbb\formie\events\SendNotificationEvent;
use verbb\formie\jobs\SendNotification;
use verbb\formie\models\Settings;

use Craft;
use craft\helpers\Json;

use yii\base\Component;
use DateInterval;
use DateTime;
use Throwable;

class Submissions extends Component
{
    // Constants
    // =========================================================================

    const EVENT_AFTER_SUBMISSION = 'afterSubmission';
    const EVENT_BEFORE_SEND_NOTIFICATION = 'beforeSendNotification';


    // Public Methods
    // =========================================================================

    /**
     * Returns a submission by it's ID.
     *
     * @param $submissionId
     * @param null $siteId
     * @return Submission|null
     */
    public function getSubmissionById($submissionId, $siteId = null)
    {
        /* @var Submission $submission */
        $submission = Craft::$app->getElements()->getElementById($submissionId, Submission::class, $siteId);
        return $submission;
    }

    /**
     * Executed after a submission has been saved.
     *
     * @param bool $success whether the submission was successful
     * @param Submission $submission
     * @see SubmissionsController::actionSubmit()
     */
    public function onAfterSubmission(bool $success, Submission $submission)
    {
        // Check if the submission is spam
        if ($submission->isSpam) {
            $success = false;
        }

        // Fire an 'afterSubmission' event
        $event = new SubmissionEvent([
            'submission' => $submission,
            'success' => $success,
        ]);
        $this->trigger(self::EVENT_AFTER_SUBMISSION, $event);

        if ($event->success) {
            // Send off some emails, if all good!
            $this->sendNotifications($event->submission);
        }
    }

    /**
     * Sends enabled notifications for a submission.
     *
     * @param Submission $submission
     */
    public function sendNotifications(Submission $submission)
    {
        // Get all enabled notifications, and push them to the queue for performance
        $form = $submission->getForm();
        $notifications = $form->getEnabledNotifications();

        foreach ($notifications as $notification) {
            // Fire a 'beforeSendNotification' event
            $event = new SendNotificationEvent([
                'submission' => $submission,
                'notification' => $notification,
            ]);
            $this->trigger(self::EVENT_BEFORE_SEND_NOTIFICATION, $event);

            if (!$event->isValid) {
                continue;
            }

            Craft::$app->getQueue()->push(new SendNotification([
                'submissionId' => $event->submission->id,
                'notificationId' => $event->notification->id,
            ]));

            // TODO: Make this a config setting
            // Formie::$plugin->getEmails()->sendEmail($event->notification, $event->submission);
        }
    }

    /**
     * Deletes incomplete submissions older than the configured interval.
     */
    public function pruneSubmissions()
    {
        /* @var Settings $settings */
        $settings = Formie::$plugin->getSettings();
        if ($settings->maxIncompleteSubmissionAge <= 0) {
            return;
        }

        $interval = new DateInterval("P{$settings->maxIncompleteSubmissionAge}D");
        $date = new DateTime();
        $date->sub($interval);

        $submissions = Submission::find()
            ->isIncomplete(true)
            ->dateUpdated('< ' . $date->format('c'))
            ->all();

        foreach ($submissions as $submission) {
            try {
                Craft::$app->getElements()->deleteElement($submission, true);
            } catch (Throwable $e) {
                Formie::error('Failed to prune submission with ID: ' . $submission->id);
            }
        }

        // Also check for spam pruning
        if ($settings->saveSpam) {
            if ($settings->spamLimit <= 0) {
                return;
            }

            $submissions = Submission::find()
                ->limit(null)
                ->offset($settings->spamLimit)
                ->isSpam(true)
                ->orderBy(['dateCreated' => SORT_DESC])
                ->all();

            foreach ($submissions as $submission) {
                try {
                    Craft::$app->getElements()->deleteElement($submission, true);
                } catch (Throwable $e) {
                    Formie::error('Failed to prune spam submission with ID: ' . $submission->id);
                }
            }
        }
    }

    /**
     * Performs spam checks on a submission.
     *
     * @param Submission $submission
     */
    public function spamChecks(Submission $submission)
    {
        /* @var Settings $settings */
        $settings = Formie::$plugin->getSettings();

        // Is it already spam? Return
        if ($submission->isSpam) {
            return;
        }

        $excludes = $this->_getArrayFromMultiline($settings->spamKeywords);

        // Build a string based on field content - much easier to find values
        // in a single string than iterate through multiple arrays
        $fieldValues = $this->_getContentAsString($submission);

        foreach ($excludes as $exclude) {
            // Check if string contains
            if (strstr($fieldValues, strtolower($exclude))) {
                $submission->isSpam = true;
                $submission->spamReason = Craft::t('formie', 'Contains banned keyword: “{c}”', ['c' => $exclude]);

                break;
            }

            // Check for IPs
            if ($submission->ipAddress && $submission->ipAddress === $exclude) {
                $submission->isSpam = true;
                $submission->spamReason = Craft::t('formie', 'Contains banned IP: “{c}”', ['c' => $exclude]);

                break;
            }
        }
    }

    /**
     * Logs spam to the Formie log.
     *
     * @param Submission $submission
     */
    public function logSpam(Submission $submission)
    {
        $fieldValues = $submission->getSerializedFieldValues();
        $fieldValues = array_filter($fieldValues);

        $error = Craft::t('formie', 'Submission marked as spam - “{r}” - {j}.', [
            'r' => $submission->spamReason,
            'j' => Json::encode($fieldValues),
        ]);

        Formie::log($error);
    }


    // Private Methods
    // =========================================================================

    /**
     * Converts a multiline string to an array.
     *
     * @param $string
     * @return array
     */
    private function _getArrayFromMultiline($string)
    {
        $array = [];

        if ($string) {
            $array = array_map('trim', explode(PHP_EOL, $string));
        }

        return $array;
    }

    /**
     * Converts a field value to a string.
     *
     * @param $submission
     * @return string
     */
    private function _getContentAsString($submission)
    {
        $fieldValues = [];

        foreach ($submission->getSerializedFieldValues() as $fieldValue) {
            // TODO: handle array values (repeater fields).
            if (!is_array($fieldValue) && (string)$fieldValue) {
                $fieldValues[] = (string)$fieldValue;
            }
        }

        return implode(' ', $fieldValues);
    }
}
