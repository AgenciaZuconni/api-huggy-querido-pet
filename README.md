# Lumen PHP Framework

[![Build Status](https://travis-ci.org/laravel/lumen-framework.svg)](https://travis-ci.org/laravel/lumen-framework)
[![Total Downloads](https://img.shields.io/packagist/dt/laravel/framework)](https://packagist.org/packages/laravel/lumen-framework)
[![Latest Stable Version](https://img.shields.io/packagist/v/laravel/framework)](https://packagist.org/packages/laravel/lumen-framework)
[![License](https://img.shields.io/packagist/l/laravel/framework)](https://packagist.org/packages/laravel/lumen-framework)

Laravel Lumen is a stunningly fast PHP micro-framework for building web applications with expressive, elegant syntax. We believe development must be an enjoyable, creative experience to be truly fulfilling. Lumen attempts to take the pain out of development by easing common tasks used in the majority of web projects, such as routing, database abstraction, queueing, and caching.

## Official Documentation

Documentation for the framework can be found on the [Lumen website](https://lumen.laravel.com/docs).

## Contributing

Thank you for considering contributing to Lumen! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Security Vulnerabilities

If you discover a security vulnerability within Lumen, please send an e-mail to Taylor Otwell at taylor@laravel.com. All security vulnerabilities will be promptly addressed.

## License

The Lumen framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# API Zuconni Shopify

Api desenvolvida em PHP utilizando o framework lumen,para baixar as dependencias do projeto é necessario utilizar o seguinte comando na pasta raiz

> composer install

Este projeto tem como finalidade realizar a integração da plataforma de chat huggy com o ecommerce shopify, para que seja enviado notificações para os clientes da loja após os mesmos efetuarem uma compra.

## Rotas de API

### GET
#### /boleto

Retorna ultimo boleto do usuário baseado em seu email

##### headers

"cliente":"example@mail.com"

#### /localizacao

Retorna o código de rastreio para o usuário baseado em seu email

##### headers

"cliente":"example@mail.com"

### POST

#### /webhooks

Utilizado nos webhooks da shopify para que sempre que um cliente realize um pedido seja enviado uma requisição para a API com os dados do pedido e enviando uma mensagem automatica de agradecimento para o cliente.

##### headers

"Content-Type":"application/json"

##### body

O body sera enviado pela própria shopipify pelo webhook, por toda via abaixo será mostrado um body de exemplo com os dados que estão sendo utilizados na API para possiveis testes de requisição

{
	"contact_email": "example@email.com",
	"billing_address": {
		"phone": "+5512999999999",
		"name": "Nome do cliente"
	},
	"note_attributes":[
		{
			"name": "URL Boleto",
            "value": "https://example.com/boleto"
		}
	],
	"line_items": [
    	{
	 		"title": "Querido Neem Pets - 2 unidades"
    	}
	]
}

#### /delivery_notification

utilizado para enviar mensagens automaticas quando um pedido sai para a entrega

##### headers

"Content-Type":"application/json"

##### body

O body sera enviado pela própria shopify mas para testes segue um exemplo de um body com os dados utilizados:

{
	"contact_email": "alessandro.junior@next.tec.br",
	"billing_address": {
		"phone": "+5512988516379",
		"name": "Alessandro Next"
	},
	"line_items": [
		{
			"title": "Querido Neem Pets - 2 unidades"
		}
	],
	"fulfillments": [
		{
			"tracking_number": "OU178156706BR",
			"tracking_url": "https:\/\/www.correios.com.br\/rastreamento"
		}
	]
}