<?php


namespace Nettools\SMS\Aws;


use \Nettools\SMS\SMSException;




/**
 * Classe to send SMS through Aws API
 */
class ApiGateway implements \Nettools\SMS\SMSGateway {

	protected $client;
	protected $sanitizeSenderId;
	
	
	
	/**
	 * Constructor
	 *
	 * @param \Aws\Sns\SnsClient $client AWS client to send sms through
	 * @param bool $sanitizeSenderId True to handle string with spaces (forbidden by AWS), and converting to camelCase
	 */
	public function __construct(\Aws\Sns\SnsClient $client, $sanitizeSenderId = true)
	{
		$this->client = $client;
		$this->sanitizeSenderId = $sanitizeSenderId;
	}
	
	
	
	/**
	 * Send SMS to several recipients
	 *
	 * @param string $msg 
	 * @param string $sender
	 * @param string[] $to Array of recipients, numbers in international format +xxyyyyyyyyyyyyy (ex. +33612345678)
	 * @param bool $transactional True if message sent is transactional ; otherwise i's promotional)
	 * @return int Returns the number of messages sent, usually the number of values of $to parameter (a multi-sms message count as 1 message)
	 * @throws \Nettools\SMS\SMSException
	 */
	function send($msg, $sender, array $to, $transactional = true)
	{
		if ( count($to) > 1 )
			throw new \Nettools\SMS\SMSException('Sending SMS to multiple recipients is not implemented yet');
		
		
		if ( $this->sanitizeSenderId )
			$sender = str_replace(' ', '', ucwords(strtolower($sender)));
		
		
		try
		{
			// publish message
			$ret = $this->client->publish([
					'Message'			=> $msg,
					'PhoneNumber'		=> $to[0],
					'MessageAttributes'	=> [
						'AWS.SNS.SMS.SenderID'	=> [
							'DataType'		=> 'String',
							'StringValue'	=> $sender
						],
						'AWS.SNS.SMS.SMSType'	=> [
							'DataType'		=> 'String',
							'StringValue'	=> ($transactional ? 'Transactional':'Promotional')
						]
					]
				]);
		}
		catch(\Aws\Exception\AwsException $e)
		{
			throw new \Nettools\SMS\SMSException($e->getMessage());
		}
		
		
		if ( $ret['MessageId'] )
			return 1;
		
		
		throw new \Nettools\SMS\SMSException('SNS unkown error : ' . print_r($ret));
	}
}

?>