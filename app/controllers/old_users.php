<?php
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 31/3/2017
 * Time: 4:00 μμ
 */

class usersController extends vanillaController {

	public function index() {
		$this->checkMethod("GET");
		$rules = [
			"step" => FILTER_SANITIZE_EMAIL,
			"error" => FILTER_SANITIZE_NUMBER_INT
		];
		$validated = $this->sanitize($this->query, $rules);

		if (isset($validated['step']) && ($validated['step'] == 'checkout' || $validated['step'] == 'cart')) {
			$this->R->step = $this->query['step'];
		} else {
			$this->R->step = null;
		}

		if (isset($validated['error'])) {
			$this->R->error = $this->query['error'];
		} else {
			$this->R->error = null;
		}

		$website_info = readJSONFile(_JSON_MODELS_PATH."data.website.json");
		$seo = [
			"title" => $this->translations['languages'][$this->lang]['connect_to_your_account_seo_title'] . ' - ' . $website_info['languages'][$this->lang]['seo_title'],
			"identifier" => htmlspecialchars($this->R->settings['project_url'] . $this->R->url['request_uri']),
			"description" => $this->translations['languages'][$this->lang]['connect_to_your_account_seo_description']
		];
		$this->R->seo = $seo;
		$this->AddJS("/ajax/user.js?v=1");
		$this->render($this->R);
	}

	public function register() {
		$this->checkMethod("GET");
		$rules = [
			"step" => FILTER_SANITIZE_EMAIL,
			"error" => FILTER_SANITIZE_NUMBER_INT
		];
		$validated = $this->sanitize($this->query, $rules);

		if (isset($validated['step']) && $validated['step'] == 'checkout') {
			$this->R->step = $this->query['step'];
		} else {
			$this->R->step = null;
		}

		if (isset($validated['error'])) {
			$this->R->error = $this->query['error'];
		} else {
			$this->R->error = null;
		}
		$eshop_info = readJSONFile(_JSON_MODELS_PATH."data.eshop.json");
		$website_info = readJSONFile(_JSON_MODELS_PATH."data.website.json");
		$seo = [
			"title" => $this->translations['languages'][$this->lang]['create_your_account_seo_title'] . ' - ' . $website_info['languages'][$this->lang]['seo_title'],
			"identifier" => htmlspecialchars($this->R->settings['project_url'] . $this->R->url['request_uri']),
			"description" => $this->translations['languages'][$this->lang]['create_your_account_seo_description']
		];
		$this->R->seo = $seo;
		$this->R->eshop = $eshop_info;
		$users_info = readJSONFile(_JSON_MODELS_PATH."data.user.json");
		$this->R->users = $users_info;

		$this->AddJS("/ajax/user.js?v=1");
		$this->render($this->R);
	}

	public function profile() {
		if (isLoggedIn()) {
			$this->AddJS("/ajax/user.js?v=1");

			$website_info = readJSONFile(_JSON_MODELS_PATH."data.website.json");
			$seo = [
				"title" => $this->translations['languages'][$this->lang]['account_seo_title'] . ' - ' . $website_info['languages'][$this->lang]['seo_title'],
				"identifier" => htmlspecialchars($this->R->settings['project_url'] . $this->R->url['request_uri']),
				"description" => $this->translations['languages'][$this->lang]['account_seo_description']
			];
			$this->R->seo = $seo;
			$this->render($this->R);
		} else {
			redirect('/'. $this->lang. '/users?error=login');
		}
	}

	public function updateProfile() {
		if (isLoggedIn()) {
			$this->checkMethod("POST");
			$this->checkRequired(["id"], $this->query);
			$rules = [
				"id" => FILTER_SANITIZE_NUMBER_INT,
				"firstname" => FILTER_UNSAFE_RAW,
				"surname" => FILTER_UNSAFE_RAW,
				"company_name" => FILTER_UNSAFE_RAW,
				"vat" => FILTER_UNSAFE_RAW,
				"vatoffice" => FILTER_UNSAFE_RAW,
				"telephone" => FILTER_UNSAFE_RAW,
				"address" => FILTER_UNSAFE_RAW,
				"job" => FILTER_UNSAFE_RAW,
			];
			$validated = $this->sanitize($this->query, $rules);

			$updateQuery = "UPDATE site_store_customers_tbl SET 
					firstname = '".$validated['firstname']."', 
					surname = '".$validated['surname']."',
					company_name = '".$validated['company_name']."', 
					vat = '".$validated['vat']."', 
					job = '".$validated['job']."', 
					telephone = '".$validated['telephone']."', 
					address = '".$validated['address']."', 
					vatoffice = '".$validated['vatoffice']."'
					WHERE id = ".$validated['id'];
			$process = $this->DB->MQ($updateQuery);
			if($process) {
				$user = $this->DB->MQ("select * from site_store_customers_tbl where id=" . $validated['id'], "one");
				setUser($user);
			}
			redirect('/'. $this->lang. '/users/profile');
		} else {
			redirect('/'. $this->lang. '/users?error=login');
		}
	}

	protected function _updateSessionUser($favourites) {
		$_SESSION['user']['favourites'] = $favourites;
	}

	public function signup() {
		// Make sure we accept only POST requests
		$this->checkMethod("POST");
		// Check for required parameters
		$this->checkRequired(["email", "password"], $this->query);
		// Rules and validation of the QueryString
		$rules = [
			"email" => FILTER_SANITIZE_EMAIL,
			"password" => FILTER_UNSAFE_RAW,
			"firstname" => FILTER_UNSAFE_RAW,
			"surname" => FILTER_UNSAFE_RAW,
			"pobox" => FILTER_UNSAFE_RAW,
			"address" => FILTER_UNSAFE_RAW,
			"city" => FILTER_UNSAFE_RAW,
			"state" => FILTER_UNSAFE_RAW,
			"country" => FILTER_UNSAFE_RAW,
			"telephone" => FILTER_UNSAFE_RAW,
			"mobilephone" => FILTER_UNSAFE_RAW,
			"step" => FILTER_UNSAFE_RAW,
			"floor" => FILTER_UNSAFE_RAW,
			"bell" => FILTER_UNSAFE_RAW
		];
		$validated = $this->sanitize($this->query, $rules);
		// Actual functionality
		$existing = $this->DB->MQ("select * from site_store_customers_tbl where email = '".$validated['email']."'", "one");
		if(is_set($existing)) {
			redirect('/'. $this->lang. '/users?error=userExists');
		} else {
			$unique_id = uniqid('', true);
			$insertQuery = "INSERT INTO site_store_customers_tbl (password, email, firstname, surname, uniqueid) VALUES('"
				.md5(md5($validated['password']))."','"
				.$validated['email']."','"
				.$validated['firstname']."','"
				.$validated['surname']."','".
				$unique_id."')";
			$process = $this->DB->MQ($insertQuery);
			if($process) {

				$this->_sendSignUpEmail($validated['email'], $unique_id);
				$this->DB->MQ("UPDATE site_store_customers_tbl SET informed = '1' WHERE uniqueid = '".$unique_id ."';");
				$query = "SELECT * FROM site_store_customers_tbl WHERE uniqueid = '".$unique_id."';";
				$user = $this->DB->MQ($query, "one");
				$insertQuery = "INSERT INTO site_store_customers_addresses_tbl (firstname, surname, address, pobox, city, state, country, telephone, mobilephone, floor, bell, `is_billing`, `is_shipping`, customer_id) VALUES(
					'".$validated['firstname']."',
					'".$validated['surname']."',
					'".$validated['address']."',
					'".$validated['pobox']."',
					'".$validated['city']."',
					'".$validated['state']."',
					'".$validated['country']."',
					'".$validated['telephone']."',
					'".$validated['mobilephone']."',
					'".$validated['floor']."',
					'".$validated['bell']."',
					'1',
					'1',
					'".$user['id']."'
					);";
				$this->DB->MQ($insertQuery);

				$query = "SELECT * FROM site_store_customers_addresses_tbl WHERE customer_id = '".$user['id']."';";
				$address = $this->DB->MQ($query, "one");

				$query = "UPDATE site_store_customers_tbl SET shipping_address = '".$address['id']."', billing_address = '".$address['id']."' WHERE id = '".$user['id'] ."';";
				$this->DB->MQ($query);

				$query = "SELECT * FROM site_store_customers_tbl WHERE uniqueid = '".$unique_id."';";
				$user = $this->DB->MQ($query, "one");
				setUser($user);

				$cartjson = $this->DB->MQ("select cart_items from site_store_carts_tbl where id='" . $this->sessionID . "'", "one");
				if ($cartjson) {
					$this->DB->MQ("UPDATE site_store_carts_tbl SET users_id = '".$user['id']."' WHERE id = '".$this->sessionID ."';");
				}

				if ($validated['step'] == 'checkout') {
					redirect('/'. $this->lang. '/checkout');
				}
				if ($validated['step'] == 'cart') {
					redirect('/'. $this->lang. '/cart');
				}
				redirect('/'. $this->lang. '/users/profile');
			} else {
				redirect('/'. $this->lang. '/users?error=login');
			}
		}
	}

	public function registration() {
		$this->checkMethod("GET");
		$this->checkRequired(["uniqueid"], $this->query);
		$rules = [
			"uniqueid" => FILTER_UNSAFE_RAW
		];
		$validated = $this->sanitize($this->query, $rules);
		$query = "SELECT * FROM site_store_customers_tbl WHERE uniqueid = '"
			.$validated['uniqueid']."';";
		$user = $this->DB->MQ($query, "one");
		if ($user) {
			$this->DB->MQ("UPDATE site_store_customers_tbl SET active = '1', validated = '1' WHERE uniqueid = '".$validated['uniqueid'] ."';");
		}

		redirect('/'. $this->lang .'/');
	}

	public function success() {
		$this->render($this->R);
	}

	function prepareProfile($user) {
		unset($user['salt']);
		unset($user['password']);
		return $user;
	}

	public function loginAction() {
		// Make sure we accept only POST requests
		$this->checkMethod("POST");
		// Check for required parameters
		$this->checkRequired(["email", "password", "step"], $this->query);
		// Rules and validation of the QueryString
		$rules = [
			"email" => FILTER_SANITIZE_EMAIL,
			"password" => FILTER_UNSAFE_RAW,
			"step" => FILTER_UNSAFE_RAW
		];
		$validated = $this->sanitize($this->query, $rules);
		$query = "SELECT * FROM site_store_customers_tbl WHERE email = '"
			.$validated['email']."' AND password='".md5(md5($validated['password']))."'";
		$user = $this->DB->MQ($query, "one");
		if (is_set($user)){
			setUser($user);

            $pre_existing_cart = $this->DB->MQ("select cart_items from site_store_carts_tbl where users_id='" .$user['id'] . "'", "one");
            $pre_existing_cartjson = json_decode($pre_existing_cart['cart_items'], true);
            if (!is_set($pre_existing_cartjson)) {
                $pre_existing_cartjson = [];
            } else {
                $this->DB->MQ("DELETE from site_store_carts_tbl WHERE users_id = '".$user['id'] ."';");
            }

            $cart = $this->DB->MQ("select cart_items from site_store_carts_tbl where id='" . $this->sessionID . "'", "one");
			if (is_set($cart)) {
				$cartjson = json_decode($cart['cart_items'], true);
				if (!is_set($cartjson)) {$cartjson = [];}

				$new_cardjson = array_merge($pre_existing_cartjson, $cartjson);
                $this->DB->MQ("UPDATE site_store_carts_tbl SET cart_items = '".json_to_db($new_cardjson)."', users_id = '".$user['id']."' WHERE id = '".$this->sessionID ."';");
			} else {
                $this->DB->MQ("INSERT INTO site_store_carts_tbl (cart_items, users_id, id, last_update) values ('".json_to_db([])."', '".$user['id']."', '".$this->sessionID."', '".date("Y-m-d H:i:s")."')");
            }

			if ($validated['step'] == 'checkout') {
				redirect('/'. $this->lang. '/checkout');
			}

			if ($validated['step'] == 'cart') {
				redirect('/'. $this->lang. '/cart');
			}

			redirect('/'. $this->lang. '/users/profile');
		} else {
			redirect('/'. $this->lang. '/users?error=wrongLogin');
		}
	}

	public function logout() {
		if (isLoggedIn()) {
			logout();
		}
		redirect('/'. $this->lang. '/users');
	}

	public function password_reset() {
		$this->checkMethod("GET");
		$website_info = readJSONFile(_JSON_MODELS_PATH."data.website.json");
		$seo = [
			"title" => $this->translations['languages'][$this->lang]['revert_password_seo_title'] . ' - ' . $website_info['languages'][$this->lang]['seo_title'],
			"identifier" => '',
			"description" => $this->translations['languages'][$this->lang]['revert_password_seo_description'],
			"image" => $this->R->settings['project_url'] . '/' . ''
		];
		$this->R->seo = $seo;

		$this->AddJS("/ajax/user.js?v=1");
		$this->render($this->R);
	}

	public function recoverPassword() {
		$this->checkMethod("POST");
		// Check for required parameters
		$this->checkRequired(["email"], $this->query);
		$rules = [
			"email" => FILTER_SANITIZE_EMAIL
		];
		$validated = $this->sanitize($this->query, $rules);
		$existingCompany = $this->DB->MQ("select * from site_store_customers_tbl where email = '".$validated['email']."'", "one");
		if(!is_set($existingCompany)) {
			setAnswer(409, sprintf($this->translations['languages'][$this->lang]['email_not_exist']), $validated['email']);
		}

		$uniqueID = '';
		$email = '';
		if (is_set($existingCompany)) {
			$uniqueID = $existingCompany['uniqueid'];
			$email = $existingCompany['email'];
		}
		$this->_sendForgotPasswordEmail($email, $uniqueID);

		setAnswer(200, $this->translations['languages'][$this->lang]['email_notify_for_change_password']);
	}

	public function resetPassword() {
		$this->checkMethod("GET");
		$this->checkRequired(["uniqueid"], $this->query);
		$rules = [
			"uniqueid" => FILTER_UNSAFE_RAW
		];
		$validated = $this->sanitize($this->query, $rules);

		$existingCompany = $this->DB->MQ("select * from site_store_customers_tbl where uniqueid = '".$validated['uniqueid']."'", "one");

		if(!is_set($existingCompany)) {
			redirect('/'. $this->lang .'/');
		}

		$email = '';
		if (is_set($existingCompany)) {
			$email = $existingCompany['email'];
		}

		$this->R->content['email'] = $email;
		$website_info = readJSONFile(_JSON_MODELS_PATH."data.website.json");
		$seo = [
			"title" => $this->translations['languages'][$this->lang]['reset_password_seo_title'] . ' - ' . $website_info['languages'][$this->lang]['seo_title'],
			"identifier" => '',
			"description" => $this->translations['languages'][$this->lang]['reset_password_seo_description'],
			"image" => $this->R->settings['project_url'] . '/' . ''
		];
		$this->R->seo = $seo;

		$this->AddJS("/ajax/user.js?v=1");
		$this->render($this->R);
	}

	public function resetPasswordAction() {
		$this->checkMethod("POST");
		$this->checkRequired(["password", "repassword", "email"], $this->query);
		$rules = [
			"password" => FILTER_UNSAFE_RAW,
			"repassword" => FILTER_UNSAFE_RAW,
			"email" => FILTER_SANITIZE_EMAIL
		];
		$validated = $this->sanitize($this->query, $rules);

		$updateQuery = "UPDATE site_store_customers_tbl SET ";
		$updateQuery .= " password = '".md5(md5($validated['password']))."' ";
		$updateQuery .= " WHERE email = '".$validated['email']."';";

		$process = $this->DB->MQ($updateQuery);

		if($process) {
			setAnswer(200, $this->translations['languages'][$this->lang]['successfull_change_of_password']);
		}
		setAnswer(409, $this->translations['languages'][$this->lang]['unseccessfull_change_of_password']);
	}

	protected function _sendForgotPasswordEmail($emailCandidate, $uniqueID)
	{
		$email_texts = readJSONFile(_JSON_MODELS_PATH."data.email.json");
		$mailService = new Mail($this->R->settings['mail']);
		$email['subject'] = $email_texts["languages"][$this->lang]['reset_title'] . ' ' . $emailCandidate;
		$email['from'] = $email_texts['from_email'];
		$email['from_name'] = $email_texts['from_name'];
		$email['recipients'] = $emailCandidate;
		$email['parameters']['preview_text'] = $email_texts["languages"][$this->lang]['reset_title'];
		$email['parameters']['text'] = $email_texts["languages"][$this->lang]['reset_text'];
        $email['parameters']['footer'] = "<img width='150' src='"._PROJECT_URL."/".$email_texts['logo']."'>";
		//parameters from now on are per case------------------
		$url = $this->R->settings['project_url']. '/' . $this->lang . '/users/resetPassword?uniqueid=' . $uniqueID;
		$email['parameters']['url'] = $url;
		$mailService->sendMail($email);
	}

	protected function _sendSignUpEmail($emailCandidate, $uniqueID)
	{
		$email_texts = readJSONFile(_JSON_MODELS_PATH."data.email.json");
		$mailService = new Mail($this->R->settings['mail']);
		$email['subject'] = $email_texts["languages"][$this->lang]['registration_title'] . ' ' . $emailCandidate;
		$email['from'] = $email_texts['from_email'];
		$email['from_name'] = $email_texts['from_name'];
		$email['recipients'] = $emailCandidate;
		$email['parameters']['preview_text'] = $email_texts["languages"][$this->lang]['registration_preview_text'];
		$email['parameters']['text'] = $email_texts["languages"][$this->lang]['registration_text'];
        $email['parameters']['footer'] = "<img width='150' src='"._PROJECT_URL."/".$email_texts['logo']."'>";

		$url = $this->R->settings['project_url']. '/' . $this->lang . '/users/registration?uniqueid=' . $uniqueID;
		$email['parameters']['url'] = $url;
		return $mailService->sendMail($email);
	}
}