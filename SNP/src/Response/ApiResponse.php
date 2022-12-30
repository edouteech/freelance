<?php


namespace App\Response;


use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponse extends JsonResponse
{
	/**
	 * ApiResponse constructor.
	 *
	 * @param string $message
	 * @param array $errors
	 * @param int $status
	 * @param array $headers
	 * @param bool $json
	 */
	public function __construct(string $message, array $errors = [], int $status = 200, array $headers = [], bool $json = false)
	{
		parent::__construct($this->format($message, $errors, $status), $status, $headers, $json);
	}

	/**
	 * Format the API response.
	 *
	 * @param string $message
	 * @param array $errors
	 *
	 * @param $status
	 * @return array
	 */
	private function format(string $message, array $errors, $status)
	{
		$response = [
			'status' => 'error',
			'status_code' => $status,
			'status_text' => preg_replace( "/\r|\n/", "", strip_tags($message)),
		];

		if ($errors)
			$response['errors'] = $errors;

		return $response;
	}
}