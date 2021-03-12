<?php

namespace Nettools\SMS\Tests;


class ApiGatewayTest extends \PHPUnit\Framework\TestCase
{
    public function testGateway()
    {
		//$api = \Aws\Sns\SnsClient
		$config = new \Nettools\Core\Misc\ObjectConfig(['sanitizeSenderId' => true]);
		
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
		$client->method('publish')->with($this->equalTo($params))->willReturn(['MessageId'=>'m.id']);
		
		
		$g = new \Nettools\SMS\Aws\ApiGateway($client, $config);
		$r = $g->sendMessage('my sms', 'TESTSENDER', ['+33601020304'], true);
		
		$this->assertEquals('m.id', $r['MessageId']);
	}
}




?>
