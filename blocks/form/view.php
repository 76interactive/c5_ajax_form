<?php defined('C5_EXECUTE') or die("Access Denied.");

  $formAction = $this->action('submit_form') . '#' . $qsID;
  $ajax_url = REL_DIR_FILES_TOOLS_BLOCKS . '/form/responder';

  $form_processing_varname = "processing_form_{$bID}";
  $template_onsubmit_funcname = "form_{$bID}_onsubmit";
  $template_onsuccess_funcname = "form_{$bID}_onsuccess";
  $template_onerror_funcname = "form_{$bID}_onerror";
  
?>

  <script type="text/javascript">
    var <?php echo $form_processing_varname; ?> = false;

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

        if (<?php echo $form_processing_varname; ?>) {
          return false; //prevent re-submission while waiting for response
        }
        <?php echo $form_processing_varname; ?> = true;
        <?php echo $template_onsubmit_funcname; ?>('#<?php echo $formDomId; ?>');

        $.ajax({
          'url': '<?php echo $ajax_url; ?>',
          'dataType': 'json',
          'data': formData,
          'type': 'POST',
          'success': function(response) {
            <?php echo $form_processing_varname; ?> = false;
            if (response.success) {
              $('#<?php echo $formDomId; ?>').clearForm();
              <?php echo $template_onsuccess_funcname; ?>('#<?php echo $formDomId; ?>', response.message);
            } else {
              <?php echo $template_onerror_funcname; ?>('#<?php echo $formDomId; ?>', response.message);
            }
          }
        });

      });
    });
  </script>
    
  <script type="text/javascript">
    function <?php echo $template_onsubmit_funcname; ?>(form) {
      $(form).find('.errors').hide().html('');
      $(form).find('input[type=submit]').hide();
      $(form).find('.indicator').show();
    }

    function <?php echo $template_onsuccess_funcname; ?>(form, thanks) {
      $(form).find('.success').html(thanks).show();
      $(form).find('.indicator').hide();
      $(form).find('.fields').hide();
    }

    function <?php echo $template_onerror_funcname; ?>(form, message) {
      $(form).find('.indicator').hide();
      $(form).find('input[type=submit]').show();
      $(form).find('.errors').html(message).show();
    }
  </script>

  <div id="formblock<?php echo $bID; ?>" class="formblock">
  <form id="<?php echo $formDomId; ?>" method="post" action="<?php echo $formAction; ?>" <?php echo ($hasFileUpload ? 'enctype="multipart/form-data"' : ''); ?>>

    <div class="success" <?php echo !$success ? 'style="display: none;"' : ''; ?>>
      <?php echo $thanksMsg; ?>
    </div>
    
    <div class="errors" <?php echo !$errors ? 'style="display: none;"' : ''; ?>>
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

    <div class="fields">
      <?php
        if (isset($questions) && count($questions) > 0) {
          foreach ($questions as $question) {
      ?>
        <div class="field field-<?php echo $question['type']; ?>">
          <label <?php echo $question['labelFor']; ?> class="<?php echo $question['labelClasses']; ?>">
            <?php echo $question['question']; ?>
            <?php if ($question['required']): ?>
              <span class="required">*</span>
            <?php endif; ?>
          </label>

          <?php echo $question['input']; ?>
        </div>
      <?php
          }
        }
      ?>
    </div><!-- .fields -->

    <input type="submit" name="Submit" class="submit" value="Submit" />

    <div class="indicator" style="display: none;">
      <img src="<?php echo ASSETS_URL_IMAGES; ?>/throbber_white_16.gif" width="16" height="16" alt="" />
      <span>Processing...</span>
    </div>

    <input name="qsID" type="hidden" value="<?php echo $qsID; ?>" />
    <input name="pURI" type="hidden" value="<?php echo $pURI; ?>" />
  </form>
  </div><!-- .formblock -->
