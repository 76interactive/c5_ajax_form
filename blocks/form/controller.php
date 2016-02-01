<?php
namespace Concrete\Package\nameofyourpackage\Block\Form;

use Loader;
use Core;
use Database;
use User;
use UserInfo;
use Config;

use \Concrete\Block\Form\MiniSurvey;

class Controller extends \Concrete\Block\Form\Controller {

  public $enableSpamHoneypot = true;
    
  public function view() {
    $miniSurvey = new MiniSurvey();
    $miniSurvey->frontEndMode = true;
    $bID = intval($this->bID);
    $qsID = intval($this->questionSetId);
    
    $formDomId = "miniSurveyView{$bID}";
    $hasFileUpload = false;
    $questionsRS = $miniSurvey->loadQuestions($qsID, $bID);
    $questions = array();

    while ($questionRow = $questionsRS->fetchRow()) {
      $question = $questionRow;
      $question['input'] = $miniSurvey->loadInputType($questionRow, false);

      if ($questionRow['inputType'] == 'fileupload') {
        $hasFileUpload = true;
      }
  
      //Make type names common-sensical
      if ($questionRow['inputType'] == 'text') {
        $question['type'] = 'textarea';
      } else if ($questionRow['inputType'] == 'field') {
        $question['type'] = 'text';
      } else {
        $question['type'] = $questionRow['inputType'];
      }
  
      //Construct label "for" (and misc. hackery for checkboxlist / radio lists)
      if ($question['type'] == 'checkboxlist') {
        $question['input'] = str_replace('<div class="checkboxPair">', '<div class="checkboxPair"><label>', $question['input']);
        $question['input'] = str_replace("</div>\n", "</label></div>\n", $question['input']); //include linebreak in find/replace string so we don't replace the very last closing </div> (the one that closes the "checkboxList" wrapper div that's around this whole question)
      } else if ($question['type'] == 'radios') {
        //Put labels around each radio items (super hacky string replacement -- this might break in future versions of C5)
        $question['input'] = str_replace('<div class="radioPair">', '<div class="radioPair"><label>', $question['input']);
        $question['input'] = str_replace('</div>', '</label></div>', $question['input']);
    
        //Make radioList wrapper consistent with checkboxList wrapper
        $question['input'] = "<div class=\"radioList\">\n{$question['input']}\n</div>\n";
      } else {
        $question['labelFor'] = 'for="Question' . $questionRow['msqID'] . '"';
      }
  
      //Remove hardcoded style on textareas
      if ($question['type'] == 'textarea') {
        $question['input'] = str_replace('style="width:95%"', '', $question['input']);
      }      
      
      $questions[] = $question;
    }

    //Prep thank-you message
    $success = ($_GET['surveySuccess'] && $_GET['qsid'] == intval($qsID));
    $thanksMsg = $this->thankyouMsg;

    //Prep error message(s)
    $errorHeader = $this->get('formResponse');
    $errors = $this->get('errors');
    $errors = is_array($errors) ? $errors : array();
        
    //Send data to the view
    $this->set('formDomId', $formDomId);
    $this->set('hasFileUpload', $hasFileUpload);
    $this->set('qsID', $qsID);
    $this->set('pURI', $pURI);
    $this->set('success', $success);
    $this->set('thanksMsg', $thanksMsg);
    $this->set('errorHeader', $errorHeader);
    $this->set('errors', $errors);
    $this->set('questions', $questions);
    $this->set('enableSpamHoneypot', $this->enableSpamHoneypot);
    $this->set('formName', $surveyBlockInfo['surveyName']); //for GA event tracking
  }

  /**
   * Users submits the completed survey.
   *
   * @param int $bID
   */
  public function action_submit_form($bID = false) {
    if ($this->enableSpamHoneypot) {
      if (!empty($_POST['message1'])) {
        // It's possible that an auto-fill helper or someone using a screenreader filled out this field,
        // so let them know that it should be left blank.
        $this->set('formResponse', t('Please correct the following errors:'));
        $this->set('errors', array(t('Error: It looks like you might be a spammer because you filled out the "Leave Blank" field. If you\'re not a spammer, please leave that field blank and try submitting again. Thanks!')));
        return;
      } else if (empty($_POST['message2']) || $_POST['message2'] != '1') {
        // It's fairly impossible that this form field got altered by accident (because it's an <input type="hidden">),
        // so don't even bother saying that there's a problem.
        $errorResponse = '<span class="confirmation">Thank you.</span>';
        $this->set('formResponse', t('Thank you.'));
        $this->set('errors', array());
        return;
      }
    }
    
    if ($this->bID != $bID) {
      return false;
    }

    $ip = Core::make('helper/validation/ip');
    $this->view();

    if ($ip->isBanned()) {
      $this->set('invalidIP', $ip->getErrorMessage());

      return;
    }

    $txt = Core::make('helper/text');
    $db = Database::connection();

    //question set id
    $qsID = intval($_POST['qsID']);
    if ($qsID == 0) {
      throw new Exception(t("Oops, something is wrong with the form you posted (it doesn't have a question set id)."));
    }

    //get all questions for this question set
    $rows = $db->GetArray("SELECT * FROM {$this->btQuestionsTablename} WHERE questionSetId=? AND bID=? order by position asc, msqID", array($qsID, intval($this->bID)));

    $errorDetails = array();

    // check captcha if activated
    if ($this->displayCaptcha) {
      $captcha = Core::make('helper/validation/captcha');
      if (!$captcha->check()) {
        $errors['captcha'] = t("Incorrect captcha code");
        $_REQUEST['ccmCaptchaCode'] = '';
      }
    }

    //checked required fields
    foreach ($rows as $row) {
      if ($row['inputType'] == 'datetime') {
        if (!isset($datetime)) {
          $datetime = Core::make('helper/form/date_time');
        }
        $translated = $datetime->translate('Question'.$row['msqID']);
        if ($translated) {
          $_POST['Question'.$row['msqID']] = $translated;
        }
      }
      if (intval($row['required']) == 1) {
        $notCompleted = 0;
        if ($row['inputType'] == 'email') {
          if (!Core::make('helper/validation/strings')->email($_POST['Question' . $row['msqID']])) {
            $errors['emails'] = t('You must enter a valid email address.');
            $errorDetails[$row['msqID']]['emails'] = $errors['emails'];
          }
        }
        if ($row['inputType'] == 'checkboxlist') {
          $answerFound = 0;
          foreach ($_POST as $key => $val) {
            if (strstr($key, 'Question'.$row['msqID'].'_') && strlen($val)) {
              $answerFound = 1;
            }
          }
          if (!$answerFound) {
            $notCompleted = 1;
          }
        } elseif ($row['inputType'] == 'fileupload') {
          if (!isset($_FILES['Question'.$row['msqID']]) || !is_uploaded_file($_FILES['Question'.$row['msqID']]['tmp_name'])) {
            $notCompleted = 1;
          }
        } elseif (!strlen(trim($_POST['Question'.$row['msqID']]))) {
          $notCompleted = 1;
        }
        if ($notCompleted) {
          $errors['CompleteRequired'] = t("Complete required fields *");
          $errorDetails[$row['msqID']]['CompleteRequired'] = $errors['CompleteRequired'];
        }
      }
    }

    //try importing the file if everything else went ok
    $tmpFileIds = array();
    if (!count($errors)) {
      foreach ($rows as $row) {
        if ($row['inputType'] != 'fileupload') {
          continue;
        }
        $questionName = 'Question'.$row['msqID'];
        if (!intval($row['required']) &&
          (
          !isset($_FILES[$questionName]['tmp_name']) || !is_uploaded_file($_FILES[$questionName]['tmp_name'])
          )
        ) {
          continue;
        }
        $fi = new FileImporter();
        $resp = $fi->import($_FILES[$questionName]['tmp_name'], $_FILES[$questionName]['name']);
        if (!($resp instanceof Version)) {
          switch ($resp) {
          case FileImporter::E_FILE_INVALID_EXTENSION:
            $errors['fileupload'] = t('Invalid file extension.');
            $errorDetails[$row['msqID']]['fileupload'] = $errors['fileupload'];
            break;
          case FileImporter::E_FILE_INVALID:
            $errors['fileupload'] = t('Invalid file.');
            $errorDetails[$row['msqID']]['fileupload'] = $errors['fileupload'];
            break;

        }
        } else {
          $tmpFileIds[intval($row['msqID'])] = $resp->getFileID();
          if (intval($this->addFilesToSet)) {
            $fs = new FileSet();
            $fs = $fs->getByID($this->addFilesToSet);
            if ($fs->getFileSetID()) {
              $fs->addFileToSet($resp);
            }
          }
        }
      }
    }

    if (count($errors)) {
      $this->set('formResponse', t('Please correct the following errors:'));
      $this->set('errors', $errors);
      $this->set('errorDetails', $errorDetails);
    } else { //no form errors
      //save main survey record
      $u = new User();
      $uID = 0;
      if ($u->isRegistered()) {
        $uID = $u->getUserID();
      }
      $q = "insert into {$this->btAnswerSetTablename} (questionSetId, uID) values (?,?)";
      $db->query($q, array($qsID, $uID));
      $answerSetID = $db->Insert_ID();
      $this->lastAnswerSetId = $answerSetID;

      $questionAnswerPairs = array();

      if (Config::get('concrete.email.form_block.address') && strstr(Config::get('concrete.email.form_block.address'), '@')) {
        $formFormEmailAddress = Config::get('concrete.email.form_block.address');
      } else {
        $adminUserInfo = UserInfo::getByID(USER_SUPER_ID);
        $formFormEmailAddress = $adminUserInfo->getUserEmail();
      }
      $replyToEmailAddress = $formFormEmailAddress;
      //loop through each question and get the answers
      foreach ($rows as $row) {
        //save each answer
        $answerDisplay = '';
        if ($row['inputType'] == 'checkboxlist') {
          $answer = array();
          $answerLong = "";
          $keys = array_keys($_POST);
          foreach ($keys as $key) {
            if (strpos($key, 'Question'.$row['msqID'].'_') === 0) {
              $answer[] = $txt->sanitize($_POST[$key]);
            }
          }
        } elseif ($row['inputType'] == 'text') {
          $answerLong = $txt->sanitize($_POST['Question'.$row['msqID']]);
          $answer = '';
        } elseif ($row['inputType'] == 'fileupload') {
          $answerLong = "";
          $answer = intval($tmpFileIds[intval($row['msqID'])]);
          if ($answer > 0) {
            $answerDisplay = File::getByID($answer)->getVersion()->getDownloadURL();
          } else {
            $answerDisplay = t('No file specified');
          }
        } elseif ($row['inputType'] == 'url') {
          $answerLong = "";
          $answer = $txt->sanitize($_POST['Question'.$row['msqID']]);
        } elseif ($row['inputType'] == 'email') {
          $answerLong = "";
          $answer = $txt->sanitize($_POST['Question'.$row['msqID']]);
          if (!empty($row['options'])) {
            $settings = unserialize($row['options']);
            if (is_array($settings) && array_key_exists('send_notification_from', $settings) && $settings['send_notification_from'] == 1) {
              $email = $txt->email($answer);
              if (!empty($email)) {
                $replyToEmailAddress = $email;
              }
            }
          }
        } elseif ($row['inputType'] == 'telephone') {
          $answerLong = "";
          $answer = $txt->sanitize($_POST['Question'.$row['msqID']]);
        } else {
          $answerLong = "";
          $answer = $txt->sanitize($_POST['Question'.$row['msqID']]);
        }

        if (is_array($answer)) {
          $answer = implode(',', $answer);
        }

        $questionAnswerPairs[$row['msqID']]['question'] = $row['question'];
        $questionAnswerPairs[$row['msqID']]['answer'] = $txt->sanitize($answer.$answerLong);
        $questionAnswerPairs[$row['msqID']]['answerDisplay'] = strlen($answerDisplay) ? $answerDisplay : $questionAnswerPairs[$row['msqID']]['answer'];

        $v = array($row['msqID'],$answerSetID,$answer,$answerLong);
        $q = "insert into {$this->btAnswersTablename} (msqID,asID,answer,answerLong) values (?,?,?,?)";
        $db->query($q, $v);
      } // endforeach;

      // include pageURL in submission
      if (isset($_POST['pageURL'])) {
        $questionAnswerPairs['pageURL']['question'] = 'Page URL';
        $questionAnswerPairs['pageURL']['answer'] = $_POST['pageURL'];
        $questionAnswerPairs['pageURL']['answerDisplay'] = $_POST['pageURL'];
      }

      $foundSpam = false;

      $submittedData = '';
      foreach ($questionAnswerPairs as $questionAnswerPair) {
        $submittedData .= $questionAnswerPair['question']."\r\n".$questionAnswerPair['answer']."\r\n"."\r\n";
      }
      $antispam = Core::make('helper/validation/antispam');
      if (!$antispam->check($submittedData, 'form_block')) {
        // found to be spam. We remove it
        $foundSpam = true;
        $q = "delete from {$this->btAnswerSetTablename} where asID = ?";
        $v = array($this->lastAnswerSetId);
        $db->Execute($q, $v);
        $db->Execute("delete from {$this->btAnswersTablename} where asID = ?", array($this->lastAnswerSetId));
      }

      if (intval($this->notifyMeOnSubmission) > 0 && !$foundSpam) {
        if (Config::get('concrete.email.form_block.address') && strstr(Config::get('concrete.email.form_block.address'), '@')) {
          $formFormEmailAddress = Config::get('concrete.email.form_block.address');
        } else {
          $adminUserInfo = UserInfo::getByID(USER_SUPER_ID);
          $formFormEmailAddress = $adminUserInfo->getUserEmail();
        }

        $mh = Core::make('helper/mail');

        // fixes a bug where multiple recipient emails were interpreted as one
        $recipientEmails = explode(',', $this->recipientEmail);
        foreach ($recipientEmails as $recipientEmail) {
          $recipientEmail = str_replace(' ', '', $recipientEmail);
          $mh->to($recipientEmail);
        }

        $mh->from($formFormEmailAddress);
        $mh->replyto($replyToEmailAddress);
        $mh->addParameter('formName', $this->surveyName);
        $mh->addParameter('questionSetId', $this->questionSetId);
        $mh->addParameter('questionAnswerPairs', $questionAnswerPairs);
        $mh->load('block_form_submission');
        $mh->setSubject(t('%s Form Submission', $this->surveyName));
        //echo $mh->body.'<br>';
        @$mh->sendMail();
      }

      if (!$this->noSubmitFormRedirect) {
        if ($this->redirectCID > 0) {
          $pg = Page::getByID($this->redirectCID);
          if (is_object($pg) && $pg->cID) {
            $this->redirect($pg->getCollectionPath());
          }
        }
        $c = Page::getCurrentPage();
        header("Location: ".Core::make('helper/navigation')->getLinkToCollection($c, true)."?surveySuccess=1&qsid=".$this->questionSetId."#formblock".$this->bID);
        exit;
      }
    }
  }
  
}