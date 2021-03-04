Leeloo API - PHP ver7.4.0
=================
[![Latest Unstable Version](https://poser.pugx.org/nouvu/leeloo/v/stable)](https://packagist.org/packages/nouvu/leeloo) [![License](https://poser.pugx.org/nouvu/leeloo/license)](//packagist.org/packages/nouvu/leeloo)

## 1. Installation
[Composer](http://getcomposer.org). Run the following command to install it:
```sh
composer require nouvu/leeloo v1.0.0-alpha
```

## 2. Example PHP code
```php
<?php

$leeloo = new \Nouvu\Api\Leeloo( [
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
], true );



$leeloo -> setSqlCallback( [
	'delete' => fn( int $id ): void => $db -> query( 'DELETE... WHERE id = ' . $id ),
	'update' => fn( int $id ): void => $db -> query( 'UPDATE... WHERE id = ' . $id ),
	'insert' => static function ( string $api, string $json_data, string $request, string $json_response ) use ( $db ): void
	{
		$db -> query( 'INSERT INTO...' );
	}
] );



$leeloo -> addTag( '__leeloo_user_PERSON_id_hash__', '__tag_name_N__' )
	-> addTag( '__tag_name_N__' )
	-> removeTag( '__tag_name_N__' )
	-> removeTag( '__tag_name_N__' );



$leeloo_order_id = $leeloo -> pending_order( [
	'email'		=> '__email__', 
	'phone'		=> '__phone__', 
	'accountId'	=> '__leeloo_user_LOGIN_id_hash__',
], '__order_offer_name_N__' );

$leeloo -> completed_order( $leeloo_order_id, '__price__', '__currency__', '__comments__' );



$leeloo -> sendTemplate( '__leeloo_user_LOGIN_id_hash__', '__template_name_N__' );
```

Create by [MouseZver](//php.ru/forum/members/40235)
