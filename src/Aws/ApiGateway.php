<?php


namespace Nettools\SMS\Aws;


use \Nettools\SMS\SMSException;




/**
 * Classe to send SMS through Aws API
 */
class ApiGateway implements \Nettools\SMS\SMSGateway {

	protected $client;
	protected $sanitizeSenderId;
	
	const AWS_TOPIC_PREFIX = 'nettools-sms-aws';
	
	
	
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
	
	
	
	public function sanitizeSender($sender)
	{
		// if there are unwanted characters in sender (AWS does not permit characters other than letters and digits ; spaces or dots are forbidden)
		if ( $this->sanitizeSenderId && preg_match('/[^A-Za-z0-9]/', $sender) )
			return str_replace(' ', '', ucwords(strtolower(preg_replace('/[^A-Za-z0-9]/', ' ', $sender))));
		else
			return $sender;
	}
	
	
	
	/**
	 * Send SMS to several recipients
	 *
	 * @param string $msg 
	 * @param string $sender ; AWS does not permit characters other than letters and digits ; spaces or dots are forbidden, and will be removed if sanitizeSenderId option is set
	 * @param string[] $to Array of recipients, numbers in international format +xxyyyyyyyyyyyyy (ex. +33612345678)
	 * @param bool $transactional True if message sent is transactional ; otherwise it's promotional)
	 * @return int Returns the number of messages sent, usually the number of values of $to parameter (a multi-sms message count as 1 message)
	 * @throws \Nettools\SMS\SMSException
	 */
	function send($msg, $sender, array $to, $transactional = true)
	{
		if ( count($to) == 0 )
			return 0;
		
		
		// publish message to several recipients ; aws doesn't allow sending to many recipients with publish to phonenumber
		if ( count($to) > 1 )
			return $this->bulkSend($msg, $sender, $to, $transactional);
		else
		{
			// if there are unwanted characters in sender (AWS does not permit characters other than letters and digits ; spaces or dots are forbidden)
			$sender = $this->sanitizeSender($sender);

			
			try
			{
				// publish message to a single recipient
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
				return count($to);


			// we shouldn't arrive here
			throw new \Nettools\SMS\SMSException('SNS unkown error : ' . print_r($ret));
		}
	}
	
	
	
	/**
	 * Send SMS to a lot of recipients (this is more optimized that calling `send` with a big array of recipients)
	 *
	 * @param string $msg 
	 * @param string $sender
	 * @param string[] $to Big array of recipients, numbers in international format +xxyyyyyyyyyyyyy (ex. +33612345678)
	 * @param bool $transactional True if message sent is transactional ; otherwise i's promotional)
	 * @return int Returns the number of SMS sent (a multi-sms message count as as many message)
	 */
	function bulkSend($msg, $sender, array $to, $transactional = true)
	{
		// if there are unwanted characters in sender (AWS does not permit characters other than letters and digits ; spaces or dots are forbidden)
		$sender = $this->sanitizeSender($sender);

		
		
		try
		{
			// creating topic
			$topic = $this->client->createTopic([
					'Name' => self::AWS_TOPIC_PREFIX . uniqid()
				]);


			// subscribing all recipients in $to array to topic created
			foreach ( $to as $num )
				$this->client->subscribe([
					   'Protocol' => 'sms',
					   'Endpoint' => $num,
					   'TopicArn' => $topic['TopicArn']
					]);


			// publish message to topic
			$ret = $this->client->publish([
					'Message'			=> $msg,
					'TopicArn'			=> $topic['TopicArn'],
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
			return count($to);


		// we shouldn't arrive here
		throw new \Nettools\SMS\SMSException('SNS unkown error : ' . print_r($ret));
	}
	
	
	
	/**
	 * Send SMS to a lot of recipients by downloading a CSV file
	 *
	 * @param string $msg 
	 * @param string $sender
	 * @param string $url Url of CSV file with recipients, numbers in international format +xxyyyyyyyyyyyyy (ex. +33612345678), first row is column headers (1 column title 'Number')
	 * @param bool $transactional True if message sent is transactional ; otherwise i's promotional)
	 * @return int Returns the number of SMS sent (a multi-sms message count as as many message)
	 */
	function bulkSendFromHttp($msg, $sender, $url, $transactional = true)
	{
		throw new SMSException('bulkSendFromHttp not implemented');
	}
}

?>