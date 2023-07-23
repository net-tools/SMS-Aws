<?php

namespace Nettools\SMS\Aws\Tests;



use \olvlvl\Given\GivenTrait;
use \PHPUnit\Framework\Assert;





class ApiGatewayTest extends \PHPUnit\Framework\TestCase
{
	use GivenTrait;

	
	
	
	public function testGateway()
    {
		$config = new \Nettools\Core\Misc\ObjectConfig((object)['sanitizeSenderId' => false]);
		
        $client = $this->createMock(\Aws\Sns\SnsClient::class);

		$params = [
			'Message'			=> 'my sms',
			'PhoneNumber'		=> '+33601020304',
			'MessageAttributes'	=> [
				'AWS.SNS.SMS.SenderID'	=> [
					'DataType'		=> 'String',
					'StringValue'	=> 'TESTSENDER'
				],
				'AWS.SNS.SMS.SMSType'	=> [
					'DataType'		=> 'String',
					'StringValue'	=> 'Transactional'
				]
			]
		];
		$client->method('__call')->with('publish', $this->equalTo([$params]))->willReturn(['MessageId'=>'m.id']);
		
		
		$g = new \Nettools\SMS\Aws\ApiGateway($client, $config);
		$r = $g->send('my sms', 'TESTSENDER', ['+33601020304'], true);
		$this->assertEquals(1, $r);
	}
	
	
	
    public function testGatewayMarkAsSentSqs()
    {
        $snsclient = $this->createMock(\Aws\Sns\SnsClient::class);
		$snsclient->method('__call')->willReturn(['MessageId'=>'m.id']);

		
		$dt = time();
		$params = [
				'MessageBody'	=> json_encode([
										'sms'			=> 'my sms',
										'sender'		=> 'TESTSENDER',
										'to'			=> ['+33601020304'],
										'transactional'	=> true,
										'timestamp'		=> $dt
									]),
				'QueueUrl'		=> 'q.url'
			];
		
        $sqsclient = $this->createMock(\Aws\Sqs\SqsClient::class);
		$sqsclient->method('__call')->with($this->equalTo('SendMessage'), $this->equalTo([$params]))->willReturn(['MessageId'=>'m.id']);
		
		
		$config = new \Nettools\Core\Misc\ObjectConfig((object)[
				'sanitizeSenderId' => false, 
				'markAsSent' => \Nettools\SMS\Aws\ApiGateway::AWS_MARK_AS_SENT_SQS,
				'sqsUrl' => 'q.url',
				'sqsClient' => $sqsclient
			]);
		$g = new \Nettools\SMS\Aws\ApiGateway($snsclient, $config);
		$r = $g->send('my sms', 'TESTSENDER', ['+33601020304'], true);
		$this->assertEquals(1, $r);
	}
	
	
	
    public function testGatewayMarkAsSentCallback()
    {
        $snsclient = $this->createMock(\Aws\Sns\SnsClient::class);
		$snsclient->method('__call')->willReturn(['MessageId'=>'m.id']);

		$ok = false;
		$config = new \Nettools\Core\Misc\ObjectConfig((object)[
				'sanitizeSenderId' => false, 
				'markAsSent' => \Nettools\SMS\Aws\ApiGateway::AWS_MARK_AS_SENT_CALLBACK, 
				'sentCallback' => function($msg, $sender, array $to, $transactional) use (&$ok) {
						if ( ($msg == 'my sms') && ($sender == 'TESTSENDER') && ($to==['+33601020304']) && ($transactional == true) )
							$ok = true;
						else
							$ok = false;
					}
			]);
		$g = new \Nettools\SMS\Aws\ApiGateway($snsclient, $config);
		$r = $g->send('my sms', 'TESTSENDER', ['+33601020304'], true);
		$this->assertEquals(1, $r);
		$this->assertEquals(true, $ok);
	}
	
	
	
    public function testGatewaySanitizeSenderId()
    {
		$config = new \Nettools\Core\Misc\ObjectConfig((object)['sanitizeSenderId' => true]);
		
        $client = $this->createMock(\Aws\Sns\SnsClient::class);
		
		$params = [
			'Message'			=> 'my sms',
			'PhoneNumber'		=> '+33601020304',
			'MessageAttributes'	=> [
				'AWS.SNS.SMS.SenderID'	=> [
					'DataType'		=> 'String',
					'StringValue'	=> 'TestSender'
				],
				'AWS.SNS.SMS.SMSType'	=> [
					'DataType'		=> 'String',
					'StringValue'	=> 'Promotional'
				]
			]
		];
		$client->method('__call')->with($this->equalTo('publish'), $this->equalTo([$params]))->willReturn(['MessageId'=>'m.id']);
		
		
		$g = new \Nettools\SMS\Aws\ApiGateway($client, $config);
		$r = $g->send('my sms', 'TEST SENDER', ['+33601020304'], false);
		$this->assertEquals(1, $r);
	}
	
	
	
    public function testGatewayRecipients()
    {
		$config = new \Nettools\Core\Misc\ObjectConfig((object)['sanitizeSenderId' => true]);
		
        $client = $this->createMock(\Aws\Sns\SnsClient::class);
		
		$params = [
			'Message'			=> 'my sms',
			'TopicArn'			=> 'topic.arn',
			'MessageAttributes'	=> [
				'AWS.SNS.SMS.SenderID'	=> [
					'DataType'		=> 'String',
					'StringValue'	=> 'TestSender'
				],
				'AWS.SNS.SMS.SMSType'	=> [
					'DataType'		=> 'String',
					'StringValue'	=> 'Promotional'
				]
			]
		];

		$client->method('__call')
			->will($this
				->given('createTopic', Assert::anything())->return(['TopicArn'=>'topic.arn'])
				->given('subscribe', array(
						[
							'Protocol'	=> 'sms',
							'Endpoint'	=> '+33601020304',
							'TopicArn'	=> 'topic.arn'
						]
					))->return(NULL)
				->given('subscribe', array(
						[
							'Protocol'	=> 'sms',
							'Endpoint'	=> '+33605060708',
							'TopicArn'	=> 'topic.arn'
						]
					))->return(NULL)
				->given('publish', [$params])->return(['MessageId'=>'m.id'])
				   
				   
			);
			
			/*->withConsecutive(
				// createTopic
				[$this->equalTo('createTopic'), $this->anything()], 

				// subscribe tel 1
				[$this->equalTo('subscribe'), $this->equalTo([
						[
							'Protocol'	=> 'sms',
							'Endpoint'	=> '+33601020304',
							'TopicArn'	=> 'topic.arn'
						]
					])
				], 
			
				// subscribe tel 2
				[$this->equalTo('subscribe'), $this->equalTo([
						[
							'Protocol'	=> 'sms',
							'Endpoint'	=> '+33605060708',
							'TopicArn'	=> 'topic.arn'
						]
					])
				], 

				// publish to topic
				[$this->equalTo('publish'), $this->equalTo([$params])]
			)*/
		//->will($this->onConsecutiveCalls(['TopicArn'=>'topic.arn'], null, null, ['MessageId'=>'m.id']));
		
		
		$g = new \Nettools\SMS\Aws\ApiGateway($client, $config);
		$r = $g->send('my sms', 'TEST SENDER', ['+33601020304', '+33605060708'], false);
		$this->assertEquals(2, $r);
	}
}




?>
