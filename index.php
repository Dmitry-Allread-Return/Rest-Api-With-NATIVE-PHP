<?php
// Заголовки для нормальной работы Api
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Credentials: true');
header('Content-type: json/application');

require "connect.php";
require "functions.php";

// Сохраняем в переменную текущий метод
$method = $_SERVER['REQUEST_METHOD'];

// Разбиваем URL запроса на части
$q = $_GET['q'];
$params = explode('/', $q);

$api = $params[0];
$type = $params[1];
$id = $params[2];
$code = $params[2];


/* Далее все достаточно просто: делаем проверку на соответствующий запрос
	и вызываем нужную функцию. Все функции описаны в файле functions.php. */
if($method === 'POST') {
	if($type === 'register') {
		addUser($connect, $_POST);
	} elseif($type === 'login') {
		login($connect, $_POST);
	} elseif($type === 'booking') {
			// Здесь магия ниже делается для того, чтобы мы корректно могли получить данные из "body" - "raw" (Речь идет о Postman, так как Api проверялось именно на нем)

			$data = file_get_contents('php://input'); //Получаем данные в JSON
			$data = json_decode($data, true); //Декодируем из JSON чтобы передать переменную в качестве параметра
			booking($connect, $data);	// Тут все понятно, вызываем функцию и передаем в нее данные 2-м параметром, которые получили выше.
	}
}

if($method === 'GET') {
	if($type === 'airport') {
		airport($connect, $_GET['query']);
	} elseif($type === 'flight') {
		flight($connect, $_GET);
	} elseif($type === 'booking' && isset($code)) {
		broneInfo($connect, $code);
	} elseif($api === 'user' && $type === 'booking') {
		// Тут мы достаем Bearer и передаем в функцию с проверкой.
		$headers = apache_request_headers();
		$token = $headers['Authorization'];
		myBrone($connect, $token);
	} elseif($api === 'user') {
		// Здесь также достаем Bearer токен.
		$headers = apache_request_headers();
		$token = $headers['Authorization'];
		infoUser($connect, $token);
	}
}