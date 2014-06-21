<?php
/**
 * Created by JetBrains PhpStorm.
 * User: rem
 * Date: 29.11.13
 * Time: 00:16
 * To change this template use File | Settings | File Templates.
 */

/**
 * Class FeedbackController
 *
 * @property User     User
 * @property Feedback Feedback
 */
class FeedbackController extends AppController {
    public $uses = array(
        'User',
        'Feedback',
    );
    public $components = array('RequestHandler', 'Email');

    /**
     * Добавить отзыв
     *
     * POST /feedback/
     *
     * Параметры:
     *     {TFeedback}
     *
     * Ответ:
     *     <отсутствует>
     */
    public function add() {
        $success = true;
        $data    = array();
        try {

	        if (!isset($this->request->data['email']) or empty($this->request->data['email'])) {
	            throw new Exception("no email specified");
	        } elseif (mb_strlen($this->request->data['email']) > 100) {
	            throw new Exception('email is too long');
	        } elseif (!$this->Feedback->validates(array('fieldList' => array('email')))) {
	            throw new Exception('email is not valid');
	        }
	
	        if (!isset($this->request->data['subject']) or empty($this->request->data['subject'])) {
	            throw new Exception('no subject specified');
	        } elseif (mb_strlen($this->request->data['subject']) > 100) {
	            throw new Exception('subject is too long');
	        }
	
	        if (!isset($this->request->data['text']) or empty($this->request->data['text'])) {
	        	throw new Exception('no text specified');
	        } elseif (mb_strlen($this->request->data['text']) > 1000) {
	        	throw new Exception('text is too long');
	        }

            $this->Feedback->create();

            $feedback_data = array();

            if ($this->currentUserId) {
                $feedback_data['user_id'] = $this->currentUserId;
            }
            $feedback_data['email']   = $this->request->data['email'];
            $feedback_data['subject'] = $this->request->data['subject'];
            $feedback_data['text']    = $this->request->data['text'];
            $feedback_data['status']  = Feedback::ID_STATUS_NEW;

            if (!$this->Feedback->save(array('Feedback' => $feedback_data))) {
                throw new Exception("can't save feedback");
            }

            $data = $this->Feedback->findById($this->Feedback->id);
            $this->set('data', $data['Feedback']);

            // уведомление для админа
            $this->Email->from     = Configure::read('support_email_from');
            $this->Email->to       = Configure::read('support_email_to');
            $this->Email->subject  = __('New feedback from user was registered');
            $this->Email->sendAs   = 'html';
            $this->Email->template = 'feedback/support_notify';

            if (!$this->Email->send()) {
                throw new Exception("Can't send email to support");
            }

            // уведомление для пользователя
            $this->Email->from     = Configure::read('support_email_from');
            $this->Email->to       = $feedback_data['email'];
            $this->Email->subject  = __('Your feedback was registered');
            $this->Email->sendAs   = 'html';
            $this->Email->template = 'feedback/user_notify';

            if (!$this->Email->send()) {
                throw new Exception("Can't send email to user");
            }

            // выставить статус успешной доставки
            if (!$this->Feedback->saveField('status', Feedback::ID_STATUS_NOTIFICATION_SENT)) {
                throw new Exception("can't change feedback status");
            }
            
            $this->setResponse($data);
        } catch (Exception $e) {
			$this->setError($e->getMessage());
		}
        
    }
}