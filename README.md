Leeloo API - PHP ver7.4.0
=================
[![Latest Unstable Version](https://poser.pugx.org/nouvu/leeloo/v/stable)](https://packagist.org/packages/nouvu/leeloo) [![License](https://poser.pugx.org/nouvu/leeloo/license)](//packagist.org/packages/nouvu/leeloo)

## 1. Installation
[Composer](http://getcomposer.org). Run the following command to install it:
```sh
composer require nouvu/leeloo v2.0.0
```

## 2. Example PHP code

```php
<?php

$person_id = '595f5d522a934035decc093d';

$account_id = '5aa7dc70839951003a597aa9';

$leeloo_config = [
	'token' => 'n97ncqx346cn__example_token__7nyc745ty9y78y...',
	'tags' => [
		'tag_name_1' => '5cn763_hash_1...',
		'tag_name_2' => '5cn763_hash_2...',
		'tag_name_3' => '5cn763_hash_3...',
	],
	'templates' => [
		'template_name_1' => '5cn763_hash_1...',
		'template_name_2' => '5cn763_hash_2...',
		'template_name_3' => '5cn763_hash_3...',
	],
	'order' => [
		'paymentCreditsId' => '5cn763_hash...',
		'offer' => [
			'offer_name_1'	=> '5cn763_hash_1...',
			'offer_name_2'	=> '5cn763_hash_2...',
		],
	]
];

/*
	@argument_2 ( default = true ) - used to save requests to the database ( tags, sendTemplate ) for the limit sending queue. 
		If the value is 'false', use the setSqlCallback method with the 'insert' parameter
*/
$leeloo = new \Nouvu\Api\Leeloo( $leeloo_config, true );

/*
	...
*/
$leeloo -> setSqlCallback( [
	'delete' => fn( int $id ) => $db -> query( 'DELETE... WHERE id = ' . $id ),
	'update' => fn( int $id ) => $db -> query( 'UPDATE... WHERE id = ' . $id ),
	'insert' => static function ( string $api, string $json_data, string $request, string $json_response ) use ( $db ): void
	{
		$db -> query( 'INSERT INTO...' );
	}
] );


/*
	Add and remove tags for your account.
	When shared, the account_id parameter can be omitted
	
	API: https://api.leeloo.ai/api/v1/accounts/account_id/add-tag
	API: https://api.leeloo.ai/api/v1/accounts/account_id/remove-tag
*/
$leeloo -> addTag( $account_id, '__tag_name_N__' )
	-> addTag( '__tag_name_N__' )
	-> removeTag( '__tag_name_N__' )
	-> removeTag( '__tag_name_N__' );

/*
	Add and remove tags to a person.
	When shared, the person_id parameter can be omitted
	
	API: https://api.leeloo.ai/api/v2/people/person_id/add-tag
	API: https://api.leeloo.ai/api/v2/people/person_id/remove-tag
*/
$leeloo -> addTagPeople( $person_id, '__tag_name_N__' )
	-> removeTagPeople( '__tag_name_N__' );

/*
	Adding an order
	
	API: https://api.leeloo.ai/api/v1/orders
*/
$leeloo_order_id = $leeloo -> orderPending( [
	'email'		=> 'mail@mail.com', 
	'phone'		=> '+380987654321', 
	'accountId'	=> $account_id,
], '__offer_name_N__' );

/*
	Order update
	
	API: https://api.leeloo.ai/api/v1/orders/order_id
*/

$leeloo -> orderUpdate( $leeloo_order_id, [
	'status' => 'RESOLVED|REJECTED', 
	'price' => 9.0, 
	'currency' => 'RUB|USD|UAH|EUR|...',
	'userComments' => 'text test'
] );

// OR short update - RESOLVED
$leeloo -> orderCompleted( $leeloo_order_id, 9.0, 'USD', 'text test' );

// OR short update - REJECTED
$leeloo -> orderFailed( $leeloo_order_id, 9.0, 'USD', 'text test' );

/*
	Sending a Message template
	
	API: https://api.leeloo.ai/api/v1/messages/send-template
*/
$leeloo -> sendTemplate( $account_id, '__template_name_N__' );
```

Create by [MouseZver](//php.ru/forum/members/40235)
