<?php
  use Concrete\Package\nameofyourpackage\Block\Form\Controller as FormController;

  defined('C5_EXECUTE') or die(_("Access Denied."));

  function sendFormAction() {
    if (empty($_POST['bID'])) {
      return error(t('Invalid form submission (missing bID)'));
    } else {
      $block = Block::getByID($_POST['bID']);
      $blockController = new FormController($block);
      $blockController->noSubmitFormRedirect = true;
      
      $redirectURL = '';

      // //Handle redirect-on-success...
      if ($blockController->redirectCID > 0) {
        $redirectPage = Page::getByID($blockController->redirectCID);
        if ($redirectPage->cID) {
          $redirectURL = Loader::helper('navigation')->getLinkToCollection($redirectPage, true);
        }
      }
      $blockController->redirectCID = 0; //reset this in block controller, otherwise it will exit before returning the data we need!
      
      try {
        $success = $blockController->action_submit_form($_POST['bID']);
        if ($success != null && $success == false) {
          return error(t('Invalid form submission (invalid block ids)'));
        }
      } catch (Exception $e) {
        return error($e->getMessage());
      }

      $fieldErrors = $blockController->get('errors');
      if (is_array($fieldErrors)) {
        foreach ($fieldErrors as $key => $value) {
          return error($fieldErrors[$key]);
        }
      }
      
      return success($blockController->thankyouMsg);
    }
  }

  function success($message) {
    echo json_encode(array(
      'success' => true,
      'message' => $message
    ));
  }

  function error($message) {
    echo json_encode(array(
      'success' => false,
      'message' => $message
    ));
  }

  sendFormAction();