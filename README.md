Ajax Form for Concrete 5.7
=============================================

Improves the built-in form block so it submits via ajax. Also uses a table-less layout for easier styling.<br>
This repository is a fork of [https://github.com/jordanlev/c5_ajax_form](https://github.com/jordanlev/c5_ajax_form)

### Installation
* Download repository as .zip
* Add the `form` directory in your package `/packages/nameofyourpackage/blocks`.
* If you want to use this block within your package add the following code:

```
    $environment = \Environment::get();
    $environment->overrideCoreByPackage('blocks/form/controller.php', $this);

    Route::register('/tools/blocks/form/responder', function() {
      include('packages/nameofyourpackage/blocks/form/tools/responder.php');
    });
```

### Use view.php as a custom template
* Optionally move `view.php` from `blocks/form/view.php` to `blocks/form/templates/nameofyourtemplate/view.php` to prevent from overriding the default template.

### Use without a package

Although I haven't tried nor documented, this block should be easy to implement without a package, directly in your `/application` directory.
