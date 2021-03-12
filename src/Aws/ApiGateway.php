<?php


namespace Nettools\SMS\Aws;


use \Nettools\SMS\SMSException;
use \Nettools\Core\Misc\AbstractConfig;




/**
 * Classe to send SMS through Aws API
 */
class ApiGateway implements \Nettools\SMS\SMSGateway {

	protected $client;
	protected $config;
	
	const AWS_TOPIC_PREFIX = 'nettools-sms-aws';
	const AWS_DEFAULT_SUBSCRIBE_RATE = 100;
	const AWS_DEFAULT_SANITIZE_SENDER_ID = true;
	
	
	
	/**
	 * Constructor
	 *
	 * @param \Aws\Sns\SnsClient $client AWS SNS client to send sms through
	 * @param \Nettools\Misc\AbstractConfig $config Config object
	 *
	 * $config must have values for :
	 * - sanitizeSenderId : true to convert senderId with spaces (forbidden by AWS), removing spaces and converting it to camelCase ; defaults to true
	 * - subscribeRate : subscrite rate (transactions/s) ; defaults to 100
	 * - markAsSent : indicates how to mark the message sent ; false (default) or an array with appropriate values :
	 *         [ 'sqsUrl' 	=> 'url',			: url of SQS queue to store sent messages in
	 *           'sqsClient'=> object   ]		: \Aws\Sqs\SqsClient object
	 *          
	 *      or [ 'callback' => callback ] 		: callback function with signature ($msg, $sender, array $to, $transactional)
	 */
	public function __construct(\Aws\Sns\SnsClient $client, AbstractConfig $config)
	{
		$this->client = $client;
		$this->config = $config;
	}
	
	
	
	/** 
	 * Removing unwanted characters in aws senderId
	 *
	 * @param string $sender
	 * @return string
	 */
	protected function sanitizeSender($sender)
	{
		// if there are unwanted characters in sender (AWS does not permit characters other than letters and digits ; spaces or dots are forbidden)
		$san = $this->config->test('sanitizeSenderId') ? $this->config->sanitizeSenderId : self::AWS_DEFAULT_SANITIZE_SENDER_ID;
		
		if ( $san && preg_match('/[^A-Za-z0-9]/', $sender) )
			return str_replace(' ', '', ucwords(strtolower(preg_replace('/[^A-Za-z0-9]/', ' ', $sender))));
		else
			return $sender;
	}
	
	
	
	/** 
	 * Mark a message as sent
	 *
	 * @param string $msg 
	 * @param string $sender ; AWS does not permit characters other than letters and digits ; spaces or dots are forbidden, and will be removed if sanitizeSenderId option is set
	 * @param string[] $to Array of recipients, numbers in international format +xxyyyyyyyyyyyyy (ex. +33612345678)
	 * @param bool $transactional True if message sent is transactional ; otherwise it's promotional)
	 * @return bool Return True if everything ok, false if there has been an error
	 */
	protected function markAsSent($msg, $sender, array $to, $transactional)
	{
		if ( !$this->config->test('markAsSent') || ($this->config->markAsSent === false) )
			return true;
		
		if ( !is_array($this->config->markAsSent) )
			return false;
		
		
		// if mark as sent with a SQS queue
		if ( array_key_exists('sqsUrl', $this->config->markAsSent) && array_key_exists('sqsClient', $this->config->markAsSent) )
		{
			try
			{
				// checking instance
				if ( !$this->config->markAsSent['sqsClient'] instanceof \Aws\Sqs\SqsClient )
					return false;
				
				
				// sending message to queue
				$ret = $this->config->markAsSent['sqsClient']->SendMessage([
						'MessageBody' => json_encode([
												'sms'	=> $msg,
												'sender'=> $sender,
												'to'	=> $to,
												'transactional'	=> $transactional,
												'timestamp'	=> time()
											]),
						'QueueUrl'	=> $this->config->markAsSent['sqsUrl']
					]);
				
				
				return is_array($ret) && array_key_exists('MessageId', $ret);
				
			}
			catch(\Aws\Exception\AwsException $e)
			{
				return false;
			}
		}
		
		
		// if mark as sent with callback
		else if ( array_key_exists('callback', $this->config->markAsSent) )
		{
			if ( is_callable($this->config->markAsSent['callback']) )
				try
				{
					return $this->config->markAsSent['callback']($msg, $sender, $to, $transactional);
				}
				catch(\Exception $e)
				{
					return false;	
				}
			else
				return false;
		}
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
				
				
				// mark as sent
				$this->markAsSent($msg, $sender, $to, $transactional);
				
				
				if ( is_array($ret) && array_key_exists('MessageId', $ret) )
					return 1;


				// we shouldn't arrive here
				throw new \Nettools\SMS\SMSException('SNS unkown error : ' . print_r($ret));
			}
			catch(\Aws\Exception\AwsException $e)
			{
				throw new \Nettools\SMS\SMSException($e->getMessage());
			}
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
			
			
			// subscribe_rate
			$rate = $this->config->test('subscribeRate') ? $this->config->subscribeRate : self::AWS_DEFAULT_SUBSCRIBE_RATE;


			// subscribing all recipients in $to array to topic created
			$n = 0;
			foreach ( $to as $num )
			{
				$n++;
				$this->client->subscribe([
					   'Protocol' => 'sms',
					   'Endpoint' => $num,
					   'TopicArn' => $topic['TopicArn']
					]);
				
				// throttling 
				if ( $n == $rate )
				{
					$n = 0;
					sleep(1);
				}
			}


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

			
			// mark as sent
			$this->markAsSent($msg, $sender, $to, $transactional);
			
			
			if ( is_array($ret) && array_key_exists('MessageId', $ret) )
				return count($to);


			// we shouldn't arrive here
			throw new \Nettools\SMS\SMSException('SNS unkown error : ' . print_r($ret));
		}
		catch(\Aws\Exception\AwsException $e)
		{
			throw new \Nettools\SMS\SMSException($e->getMessage());
		}
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