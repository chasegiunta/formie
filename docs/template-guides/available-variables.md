# Available Variables

The following Twig functions are available to call in your Twig templates.

### `craft.formie.forms(query)`
See [Form Queries](docs:getting-elements/form-queries)

```twig
{% set form = craft.formie.forms({ handle: 'contactForm' }).one() %}
```


### `craft.formie.submissions(query)`
See [Submission Queries](docs:getting-elements/submission-queries)

```twig
{% set submissions = craft.formie.submissions({ formId: 123, limit: 10 }).all() %}
```


### `craft.formie.renderForm(form, options = {})`
Renders the entire form, taking into account custom [Form Templates](docs:feature-tour/form-templates). The `form` parameter can be a [Form](docs:developers/form) object, or the handle of a form. For details on the options you can pass into the `options` parameter, see [Rendering Options](docs:template-guides/rendering-options).

```twig
{{ craft.formie.renderForm('contactForm') }}
```


### `craft.formie.renderPage(form, page)`
Renders a single page, taking into account custom [Form Templates](docs:feature-tour/form-templates). This will also include captchas (if enabled) and submit button(s).

```twig
{% set form = craft.formie.forms({ handle: 'contactForm' }).one() %}

{% for page in form.getPages() %}
    {{ craft.formie.renderPage(form, page) }}
{% endif %}
```


### `craft.formie.renderField(form, field)`
Renders a single field, taking into account custom [Form Templates](docs:feature-tour/form-templates).

```twig
{% set form = craft.formie.forms({ handle: 'contactForm' }).one() %}

{% for field in form.getFields() %}
    {{ craft.formie.renderField(form, field) }}
{% endif %}
```


### `craft.formie.registerAssets(form, options = {})`
Used to specifically register the CSS and JS for a form. Takes the same arguments as `craft.formie.renderForm`. This is particular useful for [Cached Forms](docs:template-guides/cached-forms).

```twig
{% do craft.formie.registerAssets('contactForm') %}
```


### `craft.formie.getFieldOptions(field)`
Returns a field's render options from the main options array.


### `craft.formie.getLabelPosition(field)`
Returns the label position for a field.

```twig
{% set labelPosition = craft.formie.getLabelPosition(field, form) %}

{% if labelPosition.shouldDisplay('above') %}
    <label>...</label>
    ...
```


### `craft.formie.getInstructionsPosition(field)`
Returns the instructions position for a field.

```twig
{% set instructionsPosition = craft.formie.getInstructionsPosition(field, form) %}

{% if instructionsPosition.shouldDisplay('below') %}
    <small>...</small>
    ...
```


### `craft.formie.getParsedValue(value, submission)`
For parsing a variable-string against a submission. For example, you might have a field with a handle `emailAddress`, and you could use the string `{emailAddress}` and a supplied submission to swap the template tag with the content from a submission.

```twig
{% set toEmail = craft.formie.getParsedValue('Send me an email to {emailAddress}', submission) %}
```


### `craft.formie.setCurrentSubmission(form, submission)`
Sets the current submission for a particular form. This is mostly used for multi-page forms.

