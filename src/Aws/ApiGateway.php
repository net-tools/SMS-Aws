<?php


namespace Nettools\SMS\Aws;


use \Nettools\SMS\SMSException;




/**
 * Classe to send SMS through Aws API
 */
class ApiGateway implements \Nettools\SMS\SMSGateway {

	protected $client;
	
	
	
	
	/**
	 * Constructor
	 *
	 * @param \Aws\Sns\SnsClient $client AWS client to send sms through
	 */
	public function __construct(\Aws\Sns\SnsClient $client)
	{
		$this->client = $client;
	}
	
	
	
	/**
	 * Send SMS to several recipients
	 *
	 * @param string $msg 
	 * @param string $sender
	 * @param string[] $to Array of recipients, numbers in international format +xxyyyyyyyyyyyyy (ex. +33612345678)
	 * @param string $nostop Remove STOP warning at the message end (sms sent is transactionnal ; otherwise, it's promotional)
	 * @return int Returns the number of messages sent, usually the number of values of $to parameter (a multi-sms message count as 1 message)
	 * @throws \Nettools\SMS\SMSException
	 */
	function send($msg, $sender, array $to, $nostop = true)
	{
		if ( count($to) > 1 )
			throw new \Nettools\SMS\SMSException('Sending SMS to multiple recipients is not implemented yet');
		
		
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
							'StringValue'	=> ($nostop ? 'Transactional':'Promotional')
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