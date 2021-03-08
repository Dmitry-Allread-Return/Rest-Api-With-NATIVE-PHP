<?php
//////////////////////////////////////////////////////////////////////////////////////// Функция добавления юзера
function addUser($connect, $data) {
	// Валидация входящих данных
	$first_name = filter_var(trim($data['first_name']), FILTER_SANITIZE_STRING);
	$last_name = filter_var(trim($data['last_name']), FILTER_SANITIZE_STRING);
	$phone = filter_var(trim($data['phone']), FILTER_SANITIZE_STRING);
	$password = md5(filter_var(trim($data['password']), FILTER_SANITIZE_STRING));
	$document_number = filter_var(trim($data['document_number']), FILTER_SANITIZE_STRING);

	if(mb_strlen($first_name) < 1 || 
		mb_strlen($last_name) < 1 || 
		mb_strlen($phone) < 1 ||
		mb_strlen($password) < 1) {
		http_response_code(422);
		$res = [
			'error' => [
				'code' => 422,
				'message' => "Validation error",
				'errors' => (object)[
					'error' => 'all data is required'
				]
			]
		];
		echo json_encode($res);
		exit;
	}
	if(mb_strlen($document_number) < 10 || mb_strlen($document_number) > 10) {
		http_response_code(422);
		$res = [
			'error' => [
				'code' => 422,
				'message' => "Validation error",
				'errors' => (object)[
					'error' => 'document_number must be 10 characters'
				]
			]
		];
		echo json_encode($res);
		exit;
	}

	$api_token = uniqid().uniqid().uniqid();
	// Запрос к базе
	$stmt = $connect->prepare("INSERT INTO users (first_name, last_name, phone, password, document_number, api_token) VALUES (?, ?, ?, ?, ?, ?)");
	$stmt->execute(["$first_name", "$last_name", "$phone", "$password", "$document_number", "$api_token"]);
	
	// Ответ от базы
	if($stmt->rowCount() > 0) {
		http_response_code(201);
		$res = [
			'status' => true,
			'post_id' => $connect->lastInsertId()
		];
	} else {
		http_response_code(404);
		$res = [
			'error' => [
				'code' => 404,
				'message' => "Database error",
				'errors' => [
					'error'
				]
			]
		];
	}
	echo json_encode($res);
}

//////////////////////////////////////////////////////////////////////////////////////// Аутентификация
function login($connect, $data) {
	// Валидация данных
	$phone = filter_var(trim($data['phone']),FILTER_SANITIZE_STRING);
	$password = md5(filter_var(trim($data['password']), FILTER_SANITIZE_STRING));

	if(mb_strlen($phone) < 1 || mb_strlen($password) < 1) {
		http_response_code(422);
		$res = [
			'error' => [
				'code' => 422,
				'message' => "Validation error",
				'errors' => (object)[
					'error' => 'phone and password are required'
				]
			]
		];
		echo json_encode($res);
		exit;
	}

	// Запрос в базу
	$stmt = $connect->prepare("SELECT * FROM users WHERE phone = ? AND password = ?");
	$stmt->execute(["$phone", "$password"]);
	$sel = $stmt->fetch(PDO::FETCH_ASSOC);
	// Вывод ответа
	if($stmt->rowCount() > 0) {
		http_response_code(200);
		$res = [
			'data' => [
				'token' => $sel['api_token']
			]
		];
		echo json_encode($res);
	} else {
		http_response_code(401);
		$res = [
			'error' => [
				'code' => 401,
				'message' => "Unauthorized",
				'errors' => [
					'phone' => ['phone or password incorrect']
				]
			]
		];
		echo json_encode($res);
		exit;
	}
}

//////////////////////////////////////////////////////////////////////////////////////// Запрос на поиск аэропортов
function airport($connect, $query) {
	$query = filter_var(trim($query), FILTER_SANITIZE_STRING);
	if(mb_strlen($query) < 1) {
		http_response_code(422);
		$res = [
			'error' => [
				'code' => 422,
				'message' => "Validation error",
				'errors' => (object)[
					'error' => 'query is required'
				]
			]
		];
		echo json_encode($res);
		exit;
	}

	// Запрос в базу
	$query = '%'.$query.'%';
	$stmt = $connect->prepare("SELECT * FROM airports WHERE city LIKE ? OR name LIKE ? OR iata LIKE ?");
	$stmt->execute(["$query", "$query", "$query"]);
	$sel = $stmt->fetch(PDO::FETCH_ASSOC);
	// Ответ
	if($stmt->rowCount() > 0) {
		https_response_code(200);
		$res = [
			'data' => [
				'items' => [
					'name' => $sel['name'],
					'iata' => $sel['iata']
				]
			]
		];
		echo json_encode($res);
	} else {
		http_response_code(200);
		$res = [
			'data' => [
				'items' => []
			]
		];
		echo json_encode($res);
	}
}

//////////////////////////////////////////////////////////////////////////////////////// Поиск рейсов
function flight($connect, $data) {
	$from = filter_var(trim($_GET['from']), FILTER_SANITIZE_STRING);
	$to = filter_var(trim($_GET['to']), FILTER_SANITIZE_STRING);
	$date1 = filter_var(trim($_GET['date1']), FILTER_SANITIZE_STRING);
	$date2 = filter_var(trim($_GET['date2']), FILTER_SANITIZE_STRING);
	$passengers = filter_var(trim($_GET['passengers']), FILTER_SANITIZE_STRING);

	// Проверка корректности данных
	if(mb_strlen($from) < 1 || mb_strlen($to) < 1 || mb_strlen($date1) < 1 || mb_strlen($passengers) < 1) {
		http_response_code(422);
		$res = [
			'error' => [
				'code' => 422,
				'message' => "Validation error",
				'errors' => (object)[
					'error' => 'from, to, date1, passengers is required'
				]
			]
		];
		echo json_encode($res);
		exit;
	}
	if(mb_strlen($passengers) > 8) {
		http_response_code(422);
		$res = [
			'error' => [
				'code' => 422,
				'message' => "Validation error",
				'errors' => (object)[
					'error' => 'passengers must contains min 1 max 8 number'
				]
			]
		];
		echo json_encode($res);
		exit;
	}
	
	// Проверка даты на соответствие формату
	if( !DateTime::createFromFormat('Y-m-d', $date1) ) {
		http_response_code(422);
		$res = [
			'error' => [
				'code' => 422,
				'message' => "Validation error",
				'errors' => (object)[
					'error' => 'date1 format:yyyy-mm-dd'
				]
			]
		];
		echo json_encode($res);
		exit;
	}
	// Проверка date2
	if(isset($_GET['date2'])) {
		if( !DateTime::createFromFormat('Y-m-d', $date2) ) {
			http_response_code(422);
			$res = [
				'error' => [
					'code' => 422,
					'message' => "Validation error",
					'errors' => (object)[
						'error' => 'date2 format:yyyy-mm-dd'
					]
				]
			];
			echo json_encode($res);
			exit;
		}
	}

	// Получение id из airports from
	$stmt = $connect->prepare("SELECT * FROM airports WHERE iata = ?");
	$stmt->execute(["$from"]);
	$from_obj = $stmt->fetch(PDO::FETCH_ASSOC);
	$from_id = $from_obj['id'];

	// Получение id из airports to
	$stmt = $connect->prepare("SELECT * FROM airports WHERE iata = ?");
	$stmt->execute(["$to"]);
	$to_obj = $stmt->fetch(PDO::FETCH_ASSOC);
	$to_id = $to_obj['id'];

	// Получение всех рейсов from - to
	$stmt = $connect->prepare("SELECT * FROM flights WHERE from_id = ? AND to_id = ?");
	$stmt->execute(["$from_id", "$to_id"]);
	$sel = $stmt->fetchAll(PDO::FETCH_ASSOC);
	

	for($i = 0; $i < count($sel); $i++) {
		$flights_to[] = [
			'flight_id' => $sel[$i]['id'],
			'flight_code' => $sel[$i]['flight_code'],
			'from' => [
				'city' => $from_obj['city'],
				'airport' => $from_obj['name'],
				'iata' => $from_obj['iata'],
				'date' => $date1,
				'time' => $sel[$i]['time_from']
			],
			'to' => [
				'city' => $to_obj['city'],
				'airport' => $to_obj['name'],
				'iata' => $to_obj['iata'],
				'date' => $date1,
				'time' => $sel[$i]['time_to']
			],
			'cost' => $sel[$i]['cost']
		];  
	}

	// Ответ
	if(!isset($_GET['date2'])) {
		http_response_code(200);
		$res = [
			'data' => [
				'flights_to' => $flights_to,
				'flights_back' => []
			]
		];
		echo json_encode($res);
	} else {
		// Получение всех рейсов to - from
		$stmt = $connect->prepare("SELECT * FROM flights WHERE from_id = ? AND to_id = ?");
		$stmt->execute(["$to_id", "$from_id"]);
		$sel = $stmt->fetchAll(PDO::FETCH_ASSOC);

		for($i = 0; $i < count($sel); $i++) {
			$flights_back[] = [
				'flight_id' => $sel[$i]['id'],
				'flight_code' => $sel[$i]['flight_code'],
				'from' => [
					'city' => $to_obj['city'],
					'airport' => $to_obj['name'],
					'iata' => $to_obj['iata'],
					'date' => $date2,
					'time' => $sel[$i]['time_from']
				],
				'to' => [
					'city' => $from_obj['city'],
					'airport' => $from_obj['name'],
					'iata' => $from_obj['iata'],
					'date' => $date2,
					'time' => $sel[$i]['time_to']
				],
				'cost' => $sel[$i]['cost']
			];  
		}

		http_response_code(200);
		$res = [
			'data' => [
				'flights_to' => $flights_to,
				'flights_back' => $flights_back
			]
		];
		echo json_encode($res);
	}
}


//////////////////////////////////////////////////////////////////////////////////////// Оформление бронирования
function booking($connect, $data) {
	$flight_from = $data['flight_from'];
	$flight_back = $data['flight_back'];
	$passengers = $data['passengers'];
	if(!$flight_from || !$flight_back || !$passengers) {
		http_response_code(422);
			$res = [
				'error' => [
					'code' => 422,
					'message' => "Validation error",
					'errors' => (object)[
						'error' => 'flight_from, flight_back, passengers is required'
					]
				]
			];
			echo json_encode($res);
			exit;
	}
	$f_from = $data['flight_from']['id'];
	$f_back = $data['flight_back']['id'];
	$date_from = $data['flight_from']['date'];
	$date_back = $data['flight_back']['date'];
	$code = substr(str_shuffle(implode("", range('A', 'Z'))), 0, 5);
	$stmt = $connect->prepare("INSERT INTO bookings (flight_from, flight_back, date_from, date_back, code) VALUES (?, ?, ?, ?, ?)");
	$stmt->execute(["$f_from", "$f_back", "$date_from", "$date_back", "$code"]);
	$booking_id = $connect->lastInsertId();
	
	if($stmt->rowCount() > 0) {
		foreach($passengers as $passenger) {
			$f_name = $passenger['first_name'];
			$l_name = $passenger['last_name'];
			$b_date = $passenger['birth_date'];
			$d_number = $passenger['document_number'];
			$stmt = $connect->prepare("INSERT INTO passengers (booking_id, first_name, last_name, birth_date, document_number) VALUES (?, ?, ?, ?, ?)");
			$stmt->execute(["$booking_id", "$f_name", "$l_name", "$b_date", "$d_number"]);
		}
		
		http_response_code(201);
		$res = [
			'data' => [
				'code' => $code
			]
		];
		echo json_encode($res);
	} else {
		echo "Invalid query";
	}
	
}


//////////////////////////////////////////////////////////////////////////////////////// ИНФО О БРОНИРОВАНИИ
function broneInfo($connect, $code) {

	/*ТУДА*/
	$stmt = $connect->prepare("SELECT * FROM flights JOIN bookings ON flights.id = bookings.flight_from WHERE code = ?");
	$stmt->execute(["$code"]);
	$flights_to = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
		// Получение рейсов from
		$from_id = $flights_to[0]['from_id'];
		$stmt = $connect->prepare("SELECT city, name, iata FROM airports WHERE id = ?");
		$stmt->execute(["$from_id"]);
		$from_id = $stmt->fetch(PDO::FETCH_ASSOC);

		// Получение рейсов to
		$to_id = $flights_to[0]['to_id'];
		$stmt = $connect->prepare("SELECT city, name, iata FROM airports WHERE id = ?");
		$stmt->execute(["$to_id"]);
		$to_id = $stmt->fetch(PDO::FETCH_ASSOC);
	
	/*ОБРАТНО*/
	$stmt = $connect->prepare("SELECT * FROM flights JOIN bookings ON flights.id = bookings.flight_back WHERE code = ?");
	$stmt->execute(["$code"]);
	$flights_back = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Получение итоговой стоимости
	$cost = $flights_to[0]['cost'] + $flights_back[0]['cost'];

	// Получение пассажиров
	$booking_id = $flights_to[0]['id'];
	$stmt = $connect->prepare("SELECT * FROM passengers WHERE booking_id = ?");
	$stmt->execute(["$booking_id"]);
	$passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	$filPas = [];
	foreach($passengers as $passenger) {
		$filPas[] = [
			'id' => $passenger['id'],
			'first_name' => $passenger['first_name'],
			'last_name' => $passenger['last_name'],
			'birth_date' => $passenger['birth_date'],
			'document_number' => $passenger['document_number'],
			'place_from' => $passenger['place_from'],
			'flace_back' => $passenger['flace_back']
		];
	}

	http_response_code(200);
	$res = [
		'data' => [
			'code' => $code,
			'cost' => $cost,
			"flights" => [
				[
					'flight_id' => $flights_to[0]['flight_from'],
					'flight_code' => $flights_to[0]['flight_code'],
					'from' => [
						'city' => $from_id['city'],
						'airport' => $from_id['name'],
						'iata' => $from_id['iata'],
						'date' => $flights_to[0]['date_from'],
						'time' => $flights_to[0]['time_from']
					],
					'to' => [
						'city' => $to_id['city'],
						'airport' => $to_id['name'],
						'iata' => $to_id['iata'],
						'date' => $flights_to[0]['date_from'],
						'time' => $flights_to[0]['time_to']
					],
					'cost' => $flights_to[0]['cost']
				],
				[
					'flight_id' => $flights_back[0]['id'],
					'flight_code' => $flights_back[0]['flight_code'],
					'from' => [
						'city' => $to_id['city'],
						'airport' => $to_id['name'],
						'iata' => $to_id['iata'],
						'date' => $flights_back[0]['date_back'],
						'time' => $flights_back[0]['time_from']
					],
					'to' => [
						'city' => $from_id['city'],
						'airport' => $from_id['name'],
						'iata' => $from_id['iata'],
						'date' => $flights_back[0]['date_back'],
						'time' => $flights_back[0]['time_to']
					],
					'cost' => $flights_back[0]['cost']
				]
			],
			'passengers' => $filPas

		]
	];
	echo json_encode($res);
}


//////////////////////////////////////////////////////////////////////////////////////// Получение бронирований
function myBrone($connect, $token) {
	$token = str_replace('Bearer ', '', $token);

	$stmt = $connect->prepare("SELECT * FROM users JOIN passengers ON users.document_number = passengers.document_number JOIN bookings ON bookings.id = passengers.booking_id WHERE api_token = ?");
	$stmt->execute(["$token"]);
	// Если запрос неудался...
	if($stmt->rowCount() < 1) {
		$res = [
			'error' => [
				'code' => 401,
				'message' => 'Unauthorized'
			]
		];
		http_response_code(401);
		echo json_encode($res);
		exit;
	}

	$pass = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$code = $pass[0]['code'];

	$stmt = $connect->prepare("SELECT * FROM flights JOIN bookings ON flights.id = bookings.flight_from WHERE code = ?");
	$stmt->execute(["$code"]);
	$flights_to = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
		// Получение рейсов from
		$from_id = $flights_to[0]['from_id'];
		$stmt = $connect->prepare("SELECT city, name, iata FROM airports WHERE id = ?");
		$stmt->execute(["$from_id"]);
		$from_id = $stmt->fetch(PDO::FETCH_ASSOC);

		// Получение рейсов to
		$to_id = $flights_to[0]['to_id'];
		$stmt = $connect->prepare("SELECT city, name, iata FROM airports WHERE id = ?");
		$stmt->execute(["$to_id"]);
		$to_id = $stmt->fetch(PDO::FETCH_ASSOC);
	
	/*ОБРАТНО*/
	$stmt = $connect->prepare("SELECT * FROM flights JOIN bookings ON flights.id = bookings.flight_back WHERE code = ?");
	$stmt->execute(["$code"]);
	$flights_back = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Получение итоговой стоимости
	$cost = $flights_to[0]['cost'] + $flights_back[0]['cost'];

	// Получение пассажиров
	$booking_id = $flights_to[0]['id'];
	$stmt = $connect->prepare("SELECT * FROM passengers WHERE booking_id = ?");
	$stmt->execute(["$booking_id"]);
	$passengers = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	foreach($passengers as $passenger) {
		$filPas[] = [
			'id' => $passenger['id'],
			'first_name' => $passenger['first_name'],
			'last_name' => $passenger['last_name'],
			'birth_date' => $passenger['birth_date'],
			'document_number' => $passenger['document_number'],
			'place_from' => $passenger['place_from'],
			'flace_back' => $passenger['flace_back']
		];
	}

	http_response_code(200);
	$res = [
		'data' => [
			'code' => $code,
			'cost' => $cost,
			"flights" => [
				[
					'flight_id' => $flights_to[0]['flight_from'],
					'flight_code' => $flights_to[0]['flight_code'],
					'from' => [
						'city' => $from_id['city'],
						'airport' => $from_id['name'],
						'iata' => $from_id['iata'],
						'date' => $flights_to[0]['date_from'],
						'time' => $flights_to[0]['time_from']
					],
					'to' => [
						'city' => $to_id['city'],
						'airport' => $to_id['name'],
						'iata' => $to_id['iata'],
						'date' => $flights_to[0]['date_from'],
						'time' => $flights_to[0]['time_to']
					],
					'cost' => $flights_to[0]['cost']
				],
				[
					'flight_id' => $flights_back[0]['id'],
					'flight_code' => $flights_back[0]['flight_code'],
					'from' => [
						'city' => $to_id['city'],
						'airport' => $to_id['name'],
						'iata' => $to_id['iata'],
						'date' => $flights_back[0]['date_back'],
						'time' => $flights_back[0]['time_from']
					],
					'to' => [
						'city' => $from_id['city'],
						'airport' => $from_id['name'],
						'iata' => $from_id['iata'],
						'date' => $flights_back[0]['date_back'],
						'time' => $flights_back[0]['time_to']
					],
					'cost' => $flights_back[0]['cost']
				]
			],
			'passengers' => $filPas

		]
	];
	echo json_encode($res);
}


function infoUser($connect, $token) {
	$token = str_replace('Bearer ', '', $token);

	$stmt = $connect->prepare("SELECT first_name, last_name, phone, document_number FROM users WHERE api_token = ?");
	$stmt->execute(["$token"]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	if($stmt->rowCount() < 1) {
		$res = [
			'error' => [
				'code' => 401,
				'message' => 'Unauthorized'
			]
		];
		http_response_code(401);
		echo json_encode($res);
		exit;
	}
	http_response_code(200);
	echo json_encode($user);
}