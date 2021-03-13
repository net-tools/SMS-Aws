<?php

namespace Nettools\SMS\Tests;


class ApiGatewayTest extends \PHPUnit\Framework\TestCase
{
    public function testGateway()
    {
		//$api = \Aws\Sns\SnsClient
		$config = new \Nettools\Core\Misc\ObjectConfig((object)['sanitizeSenderId' => false]);
		
        $client = $this->createMock(\Aws\Sns\SnsClient::class);
		
		/*$dt = time();
		$params = [
				'MessageBody'	=> json_encode([
										'sms'			=> 'mon sms',
										'sender'		=> 'AM63',
										'to'			=> '+33601234567',
										'transactional'	=> 1,
										'timestamp'		=> $dt
									]),
				'QueueUrl'		=> 'q.url'
			];
        $client->method('SendMessage')->with($this->equalTo($params))->willReturn(['MessageId'=>'m.id']);*/
		
/*

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

*/
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
	
	
	
    public function testGatewaySanitizeSenderId()
    {
		//$api = \Aws\Sns\SnsClient
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
		//$api = \Aws\Sns\SnsClient
		$config = new \Nettools\Core\Misc\ObjectConfig((object)['sanitizeSenderId' => true]);
		
        $client = $this->createMock(\Aws\Sns\SnsClient::class);
		
		$params1 = [
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
		$params1 = [
			'Message'			=> 'my sms',
			'PhoneNumber'		=> '+33605060708',
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
		$client->expects($this->exactly(2))->method('__call')->withConsecutive(['publish', [$params1]], ['publish', [$params2]])->willReturn(['MessageId'=>'m.id']);
		
		
		$g = new \Nettools\SMS\Aws\ApiGateway($client, $config);
		$r = $g->send('my sms', 'TEST SENDER', ['+33601020304', '+33605060708'], false);
		$this->assertEquals(2, $r);
	}
}




?>
