<?php

declare ( strict_types = 1 );

namespace Nouvu\Leeloo;

use Nouvu\Config\Config;
use Nouvu\Database\QueryStorageBank;

final class Api extends Core
{
	const API = [
		'people' => 'https://api.leeloo.ai/api/v2/people',
		'messages' => 'https://api.leeloo.ai/api/v2/messages',
		'orders' => 'https://api.leeloo.ai/api/v2/orders',
	];
	
	private /* Response | array */ $response = [];
	
	private array $configuration_keys = [ 
		'token', 
		'tags', 
		'templates', 
		'order' 
	];
	
	protected bool $cron = false;
	
	private int $id = 0;
	
	public function __construct ( array $leeloo, QueryStorageBank $storage, bool $send = true )
	{
		$this -> config = new Config( [ 
			'leeloo' => [],
			'storage_call' => [
				'delete' => static fn( int $id ) => $storage -> save( 'leeloo_delete', $id ),
				'update' => static fn( int $id, string $response ) => $storage -> save( 'leeloo_update', [ $response, $id ] ),
				'insert' => static fn( /* string | int */ ...$insert ) => $storage -> save( 'leeloo_insert', $insert ),
			],
			'send' => $send
		] );
		
		$this -> set = new Setting( $this -> config );
		
		$this -> verify( $this -> configuration_keys, $leeloo, fn( string $key ) => $this -> set -> {$key}( $leeloo[$key] ) );
	}
	
	public function send( string $link, array $data, string $request = 'POST' ): bool
	{
		if ( $this -> getData( 'send' ) )
		{
			$this -> response = $this -> stream( $link, $data, $request );
			
			if ( $this -> response -> is_error() || empty ( $this -> response -> status() ) )
			{
				if ( $this -> cron )
				{
					$this -> getData( 'storage_call.update' )( $this -> id, json_encode ( $this -> getResponse() ) );
					
					return false;
				}
			}
			else
			{
				$this -> response = [];
				
				if ( $this -> cron )
				{
					$this -> getData( 'storage_call.delete' )( $this -> id );
				}
				
				return true;
			}
		}
		else
		{
			$this -> response = [ 'expected for processing' ];
		}
		
		$this -> saveFailure();
		
		return false;
	}
	
	public function addTagPeople( string ...$args ): self
	{
		return $this -> tag( 'people', 'add', $args );
	}
	
	public function removeTagPeople( string ...$args ): self
	{
		return $this -> tag( 'people', 'remove', $args );
	}
	
	public function sendTemplate( string $account_id, string $template ): void
	{
		$template_id = $this -> getData( 'leeloo.templates.' . $template );
		
		if ( is_null ( $template_id ) )
		{
			throw new LeelooException( 'template_id not found by name: ' . $template );
		}
		
		$this -> setVars( __FUNCTION__, func_get_args (), 1 );
		
		$this -> send( self :: API['messages'] . '/send-template', [ 
			'account_id' => $account_id,
			'template_id' => $template_id
		] );
	}
	
	public function sendMessage( string $account_id, string $message, bool $sending = true ): void
	{
		$this -> setVars( __FUNCTION__, func_get_args (), 1 );
		
		$send = $this -> getData( 'send' );
		
		$this -> config -> set( 'send', fn( &$config ) => $config = $this -> cron ?: $sending );
		
		$this -> send( self :: API['messages'] . '/send-message', [ 
			'account_id' => $account_id,
			'text' => $message
		] );
		
		$this -> config -> set( 'send', fn( &$config ) => $config = $send );
	}
	
	public function updateClient( string $people_id, string $phone, string $email, array $custom_fields = null ): void
	{
		$this -> setVars( __FUNCTION__, func_get_args (), 2 );
		
		$attributes = compact ( 'phone', 'email' );
		
		if ( is_array ( $custom_fields ) )
		{
			$attributes['custom_fields'] = $custom_fields;
		}
		
		$this -> send( self :: API['people'] . '/' . $people_id, $attributes, 'PUT' );
	}
	
	public function getResponse(): array
	{
		if ( is_array ( $this -> response ) )
		{
			return $this -> response;
		}
		
		return $this -> response -> get();
	}
	
	public function get_order_id(): ?string
	{
		return $this -> getResponse()['data']['id'] ?? null;
	}
	
	public function orderPending( string $email, string $phone, string $personId, string $offer ): ?string
	{
		$offerId = $this -> getData( 'leeloo.order.offer.' . $offer );
		
		if ( is_null ( $offerId ) )
		{
			throw new LeelooException( 'offerId not found by name: ' . $offer );
		}
		
		$this -> setVars( __FUNCTION__, func_get_args (), 2 );
		
		$this -> response = $this -> stream( self :: API['orders'], [
			'paymentCreditsId'	=> $this -> getData( 'leeloo.order.paymentCreditsId' ),
			'transactionDate'	=> gmdate ( 'Y-m-d H:i' ),
			'offerId'			=> $offerId,
			'isNotifyAccount'	=> 'false',
			'email'				=> $email,
			'phone'				=> $phone, 
			'personId'			=> $personId,
		], 
		'POST' );
		
		$this -> VerifyOrderStatus();
		
		return $this -> get_order_id();
	}
	
	public function orderUpdate( string $leeloo_order_id, array $data ): void
	{
		$this -> verify( [ 'status', 'price', 'currency' ], $data );
		
		$this -> setVars( __FUNCTION__, func_get_args (), 2 );
		
		$this -> response = $this -> stream( self :: API['orders'] . '/' . $leeloo_order_id, [
			'status'			=> $data['status'],
			'price'				=> sprintf ( '%d', $data['price'] ),
			'currency'			=> $data['currency'],
			'paymentDate'		=> gmdate ( 'Y-m-d H:i' ),
			'userComments'		=> $data['userComments'] ?? null,
		], 
		'POST' );
		
		$this -> VerifyOrderStatus();
	}
	
	public function orderCompleted( string $leeloo_order_id, /* int | float */ $price, string $currency = 'USD', string $comments = 'card' ): void
	{
		$this -> orderUpdate( $leeloo_order_id, [
			'status'		=> 'RESOLVED',
			'price'			=> $price,
			'currency'		=> $currency,
			'userComments'	=> $comments,
		] );
	}
	
	public function orderFailed( string $leeloo_order_id, /* int | float */ $price, string $currency = 'USD', string $comments = 'card' ): void
	{
		$this -> orderUpdate( $leeloo_order_id, [
			'status'		=> 'REJECTED',
			'price'			=> $price,
			'currency'		=> $currency,
			'userComments'	=> $comments,
		] );
	}
	
	public function saveFailure(): void
	{
		[ 'method' => $method, 'args' => $args, 'priority' => $priority ] = $this -> getVars();
		
		if ( $method == 'orderPending' )
		{
			throw new LeelooException( 'The orderPending method is not allowed for data processing' );
		}
		
		$this -> getData( 'storage_call.insert' )( $method, json_encode ( $args ), json_encode ( $this -> getResponse() ), $priority );
	}
	
	public function cron( array $queue ): void
	{
		$this -> cron = true;
		
		$this -> id = ( int ) $queue['id'];
		
		$data = json_decode ( $queue['data'], true );
		
		try
		{
			if ( isset ( $data['status'] ) && in_array ( $queue['method'], [ 'sendMessage', 'sendTemplate', 'orderUpdate' ] ) )
			{
				$this -> getData( 'storage_call.delete' )( $this -> id );
				
				return;
			}
			
			$this -> {$queue['method']}( ...$data );
		}
		catch ( LeelooOrderFailed $e )
		{
			$this -> getData( 'storage_call.update' )( $this -> id, json_encode ( $this -> getResponse() ) );
			
			throw $e;
		}
	}
}