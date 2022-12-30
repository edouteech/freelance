<?php

namespace App\Tests\Controller;

class UserControllerTest extends AbstractControllerTest
{
	public function testLogin()
	{
		//Invalid password
		$response = $this->post('/user/login', ['login'=>'99999', 'password'=>'xxx'], false);
		$this->assertEquals(400, $response->getStatusCode(), 'Company Invalid password');

		//Company removed
		$response = $this->post('/user/login', ['login'=>'1265', 'password'=>'xxx'], false);
		$this->assertEquals(404, $response->getStatusCode(), 'Company Removed');

		//Valid caci
		//$response = $this->post('/user/login', ['login'=>'AC09179', 'password'=>'PSF1GK04']);
		//$this->assertEquals(200, $response->getStatusCode(), 'Contact Ok');

		//Valid company
		$response = $this->post('/user/login', ['login'=>'99999', 'password'=>'test'], false);
		$this->assertEquals(200, $response->getStatusCode(), 'Company Ok');
	}

	public function testFind()
	{
		$response = $this->get('/user');
		$this->assertEquals(200, $response->getStatusCode());
	}

	public function testCreate()
	{
		//create user
		$response = $this->post('/user', ['login'=>'jerome+'.uniqid().'@alaska.fr', 'password'=>'xxx'], false);
		$this->assertEquals(201, $response->getStatusCode(), 'User created');

		//get token
		$content = json_decode($response->getContent(),1);
		$this->assertIsString($content['response']['token']);

		//confirm email
		$response = $this->get('/user/confirm/'.$content['response']['token'], [], false);
		$this->assertEquals(302, $response->getStatusCode(), 'Confirm token');
	}

	public function testUpdate()
	{
		//update current user
		$response = $this->post('/user/'.$_ENV['USER_ID'], ['hasNotification'=>1, 'isAccessible'=>0]);
		$this->assertEquals(200, $response->getStatusCode(), 'User updated');
	}
}