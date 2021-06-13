<?php

declare ( strict_types = 1 );

namespace Nouvu\Leeloo;

class Response
{
	private array $opt = [
		CURLOPT_URL             => null,
		CURLOPT_POSTFIELDS		=> '[]',
		CURLOPT_RETURNTRANSFER  => true,
		CURLOPT_FOLLOWLOCATION  => true,
		CURLOPT_CUSTOMREQUEST	=> null,
		CURLOPT_HTTPHEADER      => [
			'Content-Type: application/json',
			'X-Leeloo-AuthToken: ',
		],
		CURLOPT_POST			=> true,
		CURLOPT_TIMEOUT			=> 5,
		CURLOPT_AUTOREFERER		=> true,
		CURLOPT_SSL_VERIFYPEER	=> false,
		CURLOPT_SSL_VERIFYHOST	=> false,
	];
	
	private ?array $response;
	
	private bool $err = false;
	
	public function __construct () {}
	
	public function url( string $url ): self
	{
		$this -> opt[CURLOPT_URL] = $url;
		
		return $this;
	}
	public function data( array $data ): self
	{
		$this -> opt[CURLOPT_POSTFIELDS] = json_encode ( $data );
		
		return $this;
	}
	public function request( string $request ): self
	{
		$this -> opt[CURLOPT_CUSTOMREQUEST] = $request;
		
		return $this;
	}
	public function token( string $token ): self
	{
		$this -> opt[CURLOPT_HTTPHEADER][1] .= $token;
		
		return $this;
	}
	public function init(): void
	{
		$curl = curl_init ();
		
		curl_setopt_array ( $curl, $this -> opt );
		
		$response = curl_exec ( $curl ); // string(42) "Too many requests, please try again later."
		
		curl_close ( $curl );
		
		$this -> response = json_decode ( ( string ) $response, true );
		
		if ( ! empty ( json_last_error () ) )
		{
			$this -> err = true;
			
			$this -> response = [ 'response' => $response ];
		}
	}
	public function is_error(): bool
	{
		return $this -> err;
	}
	public function status(): int
	{
		return $this -> response['status'] ?? 0;
	}
	public function get(): array
	{
		return $this -> response;
	}
}