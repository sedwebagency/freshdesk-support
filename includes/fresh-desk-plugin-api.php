<?php

/**
* Class Name: Fresh_Desk_Plugin_Api
* Fresdesk Api class
* Adding functionality to create ticket.
*/
class Fresh_Desk_Plugin_Api{

	private $apikey, $domain_url, $response, $response_status;

	/**
	* Create a new instance.
	* @param $apikey, $domain_url
	* @return void
	*/
	public function __construct($apikey, $domain_url){
		$this->apikey = $apikey;
		$this->domain_url = $domain_url;
	}	

	/**
	* Function Name: fdo_create_ticket
	* @param $email, $subject, $description
	* @return int
	*/
	function fdo_create_ticket($email,$subject,$description){
		$datafields =  array (
     									"helpdesk_ticket" =>array("subject"=>$subject,"description_html"=>$description,"email"=>$email)
     								);
 		$jsondata= json_encode($datafields);
 		$this->fdo_make_request($this->domain_url."/helpdesk/tickets.json",$jsondata);
 		if($this->response_status!= 200 || empty( $this->response )){
 			return -1;
 		}
 		return $this->response;
	}

	/**
	* Function Name: fdo_make_request
	* @param $requestUri, $payload
	* @return void
	*/
	private function fdo_make_request($requestUri,$payload){

		$user_pwd = $this->fdo_auth_method();

		$args = [
			    'headers' => [
			        'Authorization' => "Basic ".base64_encode($user_pwd),
			        "Content-type" => "application/json",
			    ],
			    'body'    => $payload,
			];
		$response = wp_remote_post( $requestUri, $args );
		$response_body = json_decode(wp_remote_retrieve_body( $response ));

		$this->response_status = $response['response']['code'];
		$this->response = isset($response_body->{'helpdesk_ticket'}->{'display_id'}) ? $response_body->{'helpdesk_ticket'}->{'display_id'} : '';
	}

	/**
	* Function Name: fdo_auth_method
	* @return string
	*/
	private function fdo_auth_method(){
		return $this->apikey.":X";
	}


}
?>