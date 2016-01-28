<?php defined('C5_EXECUTE') or die("Access Denied.");

  $formAction = $this->action('submit_form') . '#' . $qsID;
  $ajaxURL = REL_DIR_FILES_TOOLS_BLOCKS . '/form/responder';

  $formIsProcessingVariableName = "processing_form_{$bID}";
  $templateOnSubmitFunctionName = "form_{$bID}_onsubmit";
  $templateOnSuccessFunctionName = "form_{$bID}_onsuccess";
  $templateOnErrorFunctionName = "form_{$bID}_onerror";
  
?>

  <script type="text/javascript">
    var <?php echo $formIsProcessingVariableName; ?> = false;

    $(document).ready(function() {
      $('#<?php echo $formDomId; ?>').on('submit', function(e) {
        e.preventDefault();

        var formFields = $('#<?php echo $formDomId; ?>').serializeArray();
        var formData = {
          'bID': <?php echo $bID; ?>
        };

        formFields.forEach(function(pair, index) {
          formData[pair.name] = pair.value;
        });

        if (<?php echo $formIsProcessingVariableName; ?>) {
          return false; //prevent re-submission while waiting for response
        }

        <?php echo $formIsProcessingVariableName; ?> = true;
        <?php echo $templateOnSubmitFunctionName; ?>('#<?php echo $formDomId; ?>');

        $.ajax({
          'url': '<?php echo $ajaxURL; ?>',
          'dataType': 'json',
          'data': formData,
          'type': 'POST',
          'success': function(response) {
            <?php echo $formIsProcessingVariableName; ?> = false;

            if (response.success) {
              $('#<?php echo $formDomId; ?>').clearForm();
              <?php echo $templateOnSuccessFunctionName; ?>('#<?php echo $formDomId; ?>', response.message);
            } else {
              <?php echo $templateOnErrorFunctionName; ?>('#<?php echo $formDomId; ?>', response.message);
            }
          }
        });

      });
    });

    function <?php echo $templateOnSubmitFunctionName; ?>(form) {
      $(form).find('input[type=submit]').hide();
      $(form).find('.form__errors').hide().html('');
      $(form).find('.form__indicator').show();
    }

    function <?php echo $templateOnSuccessFunctionName; ?>(form, thanks) {
      $(form).find('.form__success').html(thanks).show();
      $(form).find('.form__indicator').hide();
      $(form).find('.form__fields').hide();
    }

    function <?php echo $templateOnErrorFunctionName; ?>(form, message) {
      $(form).find('input[type=submit]').show();
      $(form).find('.form__indicator').hide();
      $(form).find('.form__errors').html(message).show();
    }
  </script>

  <div id="form<?php echo $bID; ?>">
    <form id="<?php echo $formDomId; ?>" class="form" method="post" action="<?php echo $formAction; ?>" <?php echo ($hasFileUpload ? 'enctype="multipart/form-data"' : ''); ?>>

      <div class="form__success" <?php echo !$success ? 'style="display: none;"' : ''; ?>>
        <?php echo $thanksMsg; ?>
      </div>
      
      <div class="form__errors" <?php echo !$errors ? 'style="display: none;"' : ''; ?>>
        <?php echo $errorHeader; ?>
        <ul>
          <?php
            if (isset($errors) && count($errors) > 0) {
              foreach ($errors as $error) {
          ?>
          <li><?php echo $error; ?></li>
          <?php
              }
            }
          ?>
        </ul>
      </div>

      <div class="form__fields">
        <?php
          if (isset($questions) && count($questions) > 0) {
            foreach ($questions as $question) {
        ?>
          <label <?php echo $question['labelFor']; ?> class="<?php echo $question['labelClasses']; ?> form__label">
            <?php echo $question['question']; ?>
            <?php if ($question['required']): ?>
              <span class="required">*</span>
            <?php endif; ?>
          </label>

          <?php echo $question['input']; ?>
        <?php
            }
          }
        ?>

        <?php if ($enableSpamHoneypot) { ?>
          <div style="display:none">
            <label for="message"><?php echo t('Leave this field blank'); ?></label>
            <input type="text" name="message1" />
          </div>
          <input type="hidden" name="message2" value="1" />
        <?php } ?>
      </div><!-- .fields -->

      <input type="submit" name="Submit" class="form__submit" value="Submit" />

      <div class="form__indicator" style="display: none;">
        <img src="<?php echo ASSETS_URL_IMAGES; ?>/throbber_white_16.gif" width="16" height="16" alt="" />
        <span>Processing...</span>
      </div>

      <input name="qsID" type="hidden" value="<?php echo $qsID; ?>" />
      <input name="pURI" type="hidden" value="<?php echo $pURI; ?>" />
    </form>
  </div><!-- .formblock -->
