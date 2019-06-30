<?php
/**
 * @author Clinton Nzedimma
 * @package RESTful API
 */

/*error_reporting (E_ERROR);*/
// Script for RESTful API in JSON format
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
include $_SERVER['DOCUMENT_ROOT'].'/new_flimbit/engine/env/ftf.php';

// Change to POST later
if ($_SERVER['REQUEST_METHOD'] === 'POST' ||  $_SERVER['REQUEST_METHOD'] === 'GET') {
	$flimbit_api_response = []; // Flimbit API response array for telling the developer information of his response

	$api_primary_data = []; // Data to be sent to the developer

	$api_meta_data = []; // Information about the primary data


	if (isset($_REQUEST['api_key']) && API::keyExists($_REQUEST['api_key'])) {
		$flimbit_api_response = array('status' => 'SUCCESS', 'response' => 'API KEY VALID');

		$entity  =  (isset($_REQUEST['entity'])) ? $_REQUEST['entity'] : null; // Target entity, user or ad

		$m = (isset($_REQUEST['m'])) ? $_REQUEST['m'] : null; // name of method for entity

		$m_param = (isset($_REQUEST['m_param'])) ? $_REQUEST['m_param'] : null; // Parameter of method

		$m_param_key_order = [];

		$entity_factory_methods = []; //entity class methods

		$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : null; // Action to be invoked by developer


		/**
		* @block   if entity is 'ad'or 'school'
		*/
		if ($entity == 'ad' || $entity == 'school') {
			$entity_factory_methods = get_class_methods($entity.'_factory');

			if (in_array($m, $entity_factory_methods)) {
				$class_name = $entity.'_factory';
				$r = new ReflectionMethod($class_name, $m);
				$allowed_method_params = $r->getParameters();
				
				foreach ($allowed_method_params as $param) {
					 array_push($m_param_key_order, $param->getName());
				}

				$arg = [];

				foreach ($m_param_key_order as $key) {
					$arg[$key] = $m_param[$key];  
				}


				$invoke = $r->invokeArgs(new $class_name(), $arg);

				if (is_array($invoke) && array_key_exists('page_links', $invoke)) {
					unset($invoke['page_links']);
				} 
				
				$api_primary_data = $invoke;
			}	
		}


		/**
		* @block   if entity is 'single-ad'
		*/
		if ($entity == 'single-ad' && isset($_REQUEST['id'])) {
				if (Ad_factory::existsById($_REQUEST['id'])) {
					$id = $_REQUEST['id'];

					$ad = new Ad_Singleton($id);

					$api_primary_data = array('ad' => $ad->data, 'creator' => $ad->creator->public_data );
				}
			 else {
				$api_primary_data = null;
			}
										
		}




		/**
		* @block   if entity is 'user'
		*/
		if ($entity == 'user' &&  isset($_REQUEST['id'])) {
			if (User_Factory::existsById($_REQUEST['id'])) {
				$user = new User_Singleton($_REQUEST['id']);
				$api_primary_data = $user->public_data;
			} else {
				$api_primary_data = null;
			}
		
		} 	


		/**
		* @block   if action is 'auth' & entity is 'user'
		* This logs the user and returns token
		*/	

		if ($action == 'auth' && $entity == 'user') {
			if (isset($_REQUEST['phone']) &&  isset($_REQUEST['password'])) {
				$phone = sanitize_note($_REQUEST['phone']);
				$password = sanitize_note($_REQUEST['password']);

				/*Handling errors*/
				if (!User_Factory::phoneNumberExists($phone) && strlen($phone) == 11) {
					$errors[] = "This phone number $phone  does not exist !";
				}
				if (strlen($phone) != 11) {
					$errors[] = "Invalid Phone Number !";
				}
				if (User_Factory::phoneNumberExists($phone) && !User_Factory::passwordCheckByPhone($phone, $password)) {
					$errors[] = "Wrong password !";
				}	


				//Authenticate
				if (!empty($errors)) {
					// Authentication Failed
					$api_primary_data = array(
						"token" => null,
						"errors" => $errors,
						"status" => 'FAILED'
					);
				} else {
					// Authenticate Success
					$user_id = User_Factory::getByPhoneNumber('id', $phone);

					User_Factory::createToken($user_id);

					$user = new User_Singleton($user_id);

					$api_primary_data = array(
						"token" => $user->get('token'),
						"user_data" => $user->public_data,
						"errors" => null,
						"status" => 'SUCCESS'
					);
				}		
			}
		}






		/**
		* @block   if action is 'signup'
		* This registers the user and returns token
		*/	

			if ($action == 'signup') {
				$required_requests_for_sign_up = array('username', 'full_name', 'password', 'confirm_password', 'phone', 'email', 'sex', 'school_id'); // for signup

				$count_missing_data = null;

				$missing_data_fields = [];

				$filled_data_fields = [];

				$_DATA = [];

				$irrelevant_keys_for_user_request = array('api_key', 'entity', 'action');

				foreach ($_REQUEST as $key => $value) {
					if (!in_array($key, $irrelevant_keys_for_user_request)) {
						$_DATA[$key] = $value;
					}
				}

				foreach ($_DATA as $key => $value) {
					array_push($filled_data_fields, $key); 
				}


				$missing_data_fields = array_diff($required_requests_for_sign_up, $filled_data_fields);

				if (true) {
					/* Initializing data */
					$username = (isset($_DATA['username'])) ? sanitize_note($_DATA['username']) :null;
					$full_name =  (isset($_DATA['full_name'])) ? sanitize_note($_DATA['full_name']) :null;
					$password = (isset($_DATA['password'])) ? sanitize_note($_DATA['password']) :null;
					$confirm_password = (isset($_DATA['confirm_password'])) ? sanitize_note($_DATA['confirm_password']) :null;
					$phone = (isset($_DATA['phone'])) ? sanitize_note(strip_whitespace($_DATA['phone'])) :null;
					$email = (isset($_DATA['email'])) ? sanitize_note($_DATA['email']) :null;
					$sex = (isset($_DATA['sex'])) ? sanitize_note($_DATA['sex']) :null;
					$school_id = (isset($_DATA['school_id'])) ? sanitize_note($_DATA['school_id']) :null;


					/* Errors for signup action */
					if (strlen($username) < 3) {
						$errors[] = "Your username should not be less than 3 characters ! ";
					}
					if (!sanitize_username($username)) {
						$errors[] = "Username should not contain space or special characters !";
					}	
					if (check_for_whitespace($username)) {
						$errors[] = "Your username should not contain any space ! ";
					}
					if (!strHasLettersOnly($full_name)) {
						$errors[] = "Your Full name should contain only letters [A-Za-z] !";
					}
					if (strlen($full_name)<3) {
						$errors[] = "Your full name should not be less than 3 characters !";
					}
					if (strlen($password) < 5) {
						$errors[] = "Your password should not be less than 5 characters !";
					}
					if (strlen($password) >= 5 && $password != $confirm_password) {
						$errors[] = "Your passwords do not match !";
					}
					if (!is_phone_number($phone)) {
						$errors[] = "Invalid phone number!";
					}
					if (strlen($phone) != 11 && is_phone_number($phone)) {
						$errors[] = "Your phone number should contain 11 digits !";
					}
					if (User_Factory::usernameExists($username)) {
						$errors[] = "Username $username already taken, choose another username !";
					}
					if (User_Factory::emailExists($email)) {
						$errors[] = "Email $email has aleady been used !";
					}
					if (!sanitize_email($email)) {
						$errors[] = "Invalid Email !";
					}
					if (User_Factory::phoneNumberExists($phone)) {
						$errors[] = "This phone $phone has already been used !";
					}
					if (!School_Factory::existsById($school_id)) {
						$errors[] = "Invalid input for school !";
					}
					if($sex != "male" && $sex != "female" && $sex!=null) {
						$errors[] = "Invalid Sex !";
					}
					if ($sex == null || $sex =="") {
						$errors[] = "Please select sex";	
					}



					//Processing Signup
					if (!empty($errors)) {
					 	$api_primary_data = array("errors" => $errors, "missing_data" => $missing_data_fields, "status" => 'FAILED');
					 } else {
					 		User_Factory::signUp($_DATA);

							$user_id = User_Factory::getByUsername('id', $username);

							User_Factory::createToken($user_id);

							$user = new User_Singleton($user_id);

							$api_primary_data = array(
								"token" => $user->get('token'),
								"status" => 'SUCCESS',
								"errors" => null
							);
					 }

				} else {
					$api_primary_data = null;
				}

		}

		if ($action == 'post-ad') {
			$token = (isset($_REQUEST['token'])) ? $_REQUEST['token'] : null; // user token

			$required_requests_for_post_ad	= array('category', 'title', 'description', 'price', 'b64img');		
			if (User_Factory::tokenExists($token)) {
				$user_id = User_Factory::getByToken('id', $token);
				$user =  new User_Singleton($user_id);

				$count_missing_data = null;

				$missing_data_fields = [];

				$filled_data_fields = [];

				$_DATA = [];

				$irrelevant_keys_for_user_request = array('api_key', 'entity', 'action', 'token');

				foreach ($_REQUEST as $key => $value) {
					if (!in_array($key, $irrelevant_keys_for_user_request)) {
						$_DATA[$key] = $value;
					}
				}

				foreach ($_DATA as $key => $value) {
					array_push($filled_data_fields, $key); 
				}


				$missing_data_fields = array_diff($required_requests_for_post_ad, $filled_data_fields);
				$count_missing_data = count($missing_data_fields);

				if (true) { 
					// Allowed categories
					$categories = array(
						"electronics" => "electronics",
						"fashion" => "fashion",
						"services" => "services",
						"phones" => "phones", 
						"food" => "food",
						"books & school items" => "books-school-items",
						"sport, hobbies & art" => "sport-hobbies-art"		
					); 

					$category =  isset($_DATA['category'])? sanitize_note($_DATA['category']) : null;
					$title =  isset($_DATA['title'])? sanitize_note($_DATA['title']) : null;
					$description = isset($_DATA['description'])?  sanitize_note($_DATA['description']) : null;
					$price = isset($_DATA['price'])? intval($_DATA['price']) : null;
					$negotiable = isset($_DATA['negotiable'])? sanitize_note($_DATA['negotiable']) : null;
					$b64img = isset($_DATA['b64img'])? $_DATA['b64img'] : null;

					$upload = new Upload("image","placement_img", 4, $b64img);


					/*errors*/
					if ($count_missing_data > 0) {
						$errors[] = "Please fill all required fields";
					}
					if (strlen($title) > 50) {
						$errors[]='The title should not exceed 50 characters !';
					}
					if (strlen($title) < 10) {
						$errors[]='The title should not be less than 10 characters !';
					}
					if (strlen($description) > 160){
						$errors[]='The description should not exceed 160 characters';
					}	
					if (strlen($description) < 10){
						$errors[]='The description should not be less than 10 characters !';
					}
					if (!in_array($category, $categories)) {
						$errors[] = 'Invalid category !';
					}							
					if(!is_int($price)){
						$errors[]='Your price should be a number !';
					}						
					if ($price < 10 && is_int($price)){
						$errors[]='The miniumum price allowed is  &#8358;10  !';
					}	
					if ($price > 10000000){
						$errors[]='The maximum price allowed is &#8358;10,000,000 !';
					}
					if ($upload->sizeIsLarge() && !$upload->hasError()) {
						$errors[] = "Please upload image below 4.0MB !";
					}
					if ($upload->hasError() && !$upload->isEmpty()) {
						$errors[] = "There is an error in the photo you uploaded !";
					}
					if (!$upload->isImage() && !$upload->isEmpty()) {
						$errors[] = "The file you uploaded is not an image !";
					}
					if ($upload->isEmpty()) {
						$errors[] = "No Image !";
					}

					
					//Processing Post ad
					if (!empty($errors)) {
					 	$api_primary_data = array("errors" => $errors, "missing_data" => $missing_data_fields, "status" => 'FAILED');
					 } else  {
					 	Ad_Factory::createNew($_DATA,$u->get('id'), $u->get('school_id'),$upload);
					 	$api_primary_data = array("errors" => null, "status" => 'SUCCESS');
					 }

				}

				$api_primary_data['token_status'] = 'VALID';
			} else {
				$api_primary_data['token_status'] = 'INVALID';
			}
		}






		if ($action == 'user-settings') {
			$sub_action = (isset($_REQUEST['sub_action'])) ? $_REQUEST['sub_action'] : null; 
			$token = (isset($_REQUEST['token'])) ? $_REQUEST['token'] : null; // user token

			if (User_Factory::tokenExists($token)) {
				$user_id = User_Factory::getByToken('id', $token);

				$user =  new User_Singleton($user_id);


				$irrelevant_keys_for_user_request = array('api_key', 'entity', 'action', 'token', 'sub_action');


				/**
				* @block Edit Profile Details
				*
				*/
				if ($sub_action == 'edit-profile') {

					$required_requests_for_edit_profile = array('full_name', 'email', 'sex');

					$count_missing_data = null;

					$missing_data_fields = [];

					$filled_data_fields = [];

					$_DATA = [];

					foreach ($_REQUEST as $key => $value) {
						if (!in_array($key, $irrelevant_keys_for_user_request)) {
							$_DATA[$key] = $value;
						}
					}

					foreach ($_DATA as $key => $value) {
						array_push($filled_data_fields, $key); 
					}


					$missing_data_fields = array_diff($required_requests_for_edit_profile, $filled_data_fields);
					$count_missing_data = count($missing_data_fields);					

					// Data for edit profile
					$full_name =  (isset($_DATA['full_name'])) ? sanitize_note($_DATA['full_name']) :null;
					$email = (isset($_DATA['email'])) ? sanitize_note($_DATA['email']) :null;
					$sex = (isset($_DATA['sex'])) ? sanitize_note($_DATA['sex']) :null;


					/*errors*/
					if ($count_missing_data > 0) {
						$errors[] = "Please fill all fields !";
					}
					if (!strHasLettersOnly($full_name)) {
						$errors[] = "Your Full name should contain only letters [A-Za-z] !";
					}
					if (strlen($full_name)<3) {
						$errors[] = "Your full name should not be less than 3 characters !";
					}
					if (User_Factory::emailExists($email) && $email != $user->get('email')) {
						$errors[] = "Email  $email  has aleady been used !";
					}	
					if (!sanitize_email($email)) {
						$errors[] = "Invalid Email";
					}	
					if($sex != 'male' && $sex != 'female') {
						$errors[] = "Invalid Gender !";
					}


					/*modify profile*/
					if (!empty($errors)) {
						$api_primary_data = array("errors" => $errors, "missing_data" => $missing_data_fields, "status" => 'FAILED');
					} else {
						$api_primary_data = array("errors" => null,"status" => 'SUCCESS');
						$user->modifyProfile($_DATA);
					}

				}



				/**
				* @block Upload new profile picture
				*
				*/
				if ($sub_action == 'upload-image') {
					$_DATA = $_REQUEST;

					$b64img = isset($_DATA['b64img'])? $_DATA['b64img'] : null;

					$upload = new Upload('image', 'profilePicInput', 2.0, $b64img);

					/*errors*/
					if ($upload->sizeIsLarge() && !$upload->hasError()) {
						$errors[] = "Please upload image below 2.0MB !";
					}
					if ($upload->hasError()) {
						$errors[] = "There is an error in the photo you uploaded !";
					}
					if (!$upload->isImage() && !$upload->isEmpty()) {
						$errors[] = "The file you uploaded is not an image !";
					}
					if ($upload->isEmpty()) {
						$errors[] = "No Image !";
					}


					// Errors
					if (!empty($errors)) {
						$api_primary_data = array("errors" => $errors,  "status" => 'FAILED');
					} else {
							$upload->pushBase64ImageTo(ROOT."/avatars");
							$user->changeDpData($upload->data['new_file_name']);
							$api_primary_data = array("errors" => null,"status" => 'SUCCESS');	
					}						

				}

				/**
				* @block Edit Password
				*
				*/
				if ($sub_action == 'edit-password' ) {
					$required_requests_for_edit_password = array('old_password','new_password', 'confirm_password');

					$count_missing_data = null;

					$missing_data_fields = [];

					$filled_data_fields = [];

					$_DATA = [];

					foreach ($_REQUEST as $key => $value) {
						if (!in_array($key, $irrelevant_keys_for_user_request)) {
							$_DATA[$key] = $value;
						}
					}

					foreach ($_DATA as $key => $value) {
						array_push($filled_data_fields, $key); 
					}


					$missing_data_fields = array_diff($required_requests_for_edit_password, $filled_data_fields);
					$count_missing_data = count($missing_data_fields);


					//Data for change password
					$old_password =  (isset($_DATA['old_password'])) ? sanitize_note($_DATA['old_password']) :null;
					$new_password =  (isset($_DATA['new_password'])) ? sanitize_note($_DATA['new_password']) :null;
					$confirm_password =  (isset($_DATA['confirm_password'])) ? sanitize_note($_DATA['confirm_password']) :null;


					/*errors*/
					if (!password_verify($old_password, $user->get('password'))) {
						$errors[] = "Wrong Password !";
					}
					if ($new_password == $old_password && password_verify($old_password, $user->get('password')) ) {
						$errors[] = "You cannot use old password !";
					}
					if (strlen($new_password) >= 5 && $new_password != $confirm_password && password_verify($old_password, $user->get('password')) ) {
						$errors[] = "Your passwords do not match !";
					}
					if (strlen($new_password)<5 && password_verify($old_password, $user->get('password')) ) {
						$errors[] = "Password must be 4 characters";
					}



					/*change password*/
					if (!empty($errors)) {
						$api_primary_data = array("errors" => $errors, "missing_data" => $missing_data_fields, "status" => 'FAILED');
					} else {
						$user->changePassword($new_password);
						$api_primary_data = array("errors" => null,"status" => 'SUCCESS');
					}
				}

				$api_primary_data['token_status'] = 'VALID';
			}	else {
				$api_primary_data['token_status'] = 'INVALID';
			}		


		} 


			// MAIN API RESPONSE
			echo json_encode(
				array(
					'api_primary_data' => $api_primary_data,					
					'flimbit_api_response'=> $flimbit_api_response
				)
			);		

	}


	if (isset($_REQUEST['api_key']) && !API::keyExists($_REQUEST['api_key'])) {
		$api_primary_data = "ACCESS DENIED";
	   	$flimbit_api_response = array('status' => 'SUCCESS', 'response' => 'INVALID API KEY' );
		echo json_encode(					
			array (
				'api_primary_data' => $api_primary_data,					
				'flimbit_api_response'=> $flimbit_api_response	
			)
		);
	}

}	
?>