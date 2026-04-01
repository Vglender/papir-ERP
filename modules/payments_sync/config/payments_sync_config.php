<?php

return [
    'default_agent_id' => '565176ae-920a-11eb-0a80-042f000cdb9f',

    'paymentin_state_id'  => '4e5eaa29-751f-11ec-0a80-00670008f611',
    'paymentout_state_id' => 'd1451432-842a-11ec-0a80-02f9000ebb6b',

    'paymentin_inner_attribute_id'  => '3e58b958-92f0-11eb-0a80-00da000a0e6a',
    'paymentout_inner_attribute_id' => '3e723523-92f0-11eb-0a80-06f1000a13ba',

    'customerorder_liqpay_attribute_id' => '01880d85-497f-11ec-0a80-049c00181c41',

    'mono_name_patterns' => [
        'Получатель: ',
        'Отправитель: ',
        'От ',
        'Від: ',
    ],

    'pb_name_cleanup_patterns' => [
        '/\(ТОВ\)/u',
        '/\(ЛТД\)/u',
        '/,/u',
        '/"/u',
        '/\(/u',
        '/\)/u',
        '/\(ПІДПРИЄМЕЦЬ\)/u',
        '/\(НВП\)/u',
        '/\(ФІРМА\)/u',
        '/\(ФОП\)/u',
        '/\( АТ \)/u',
        '/\( ВАТ \)/u',
        '/\( ПАТ \)/u',
        '/\( ПП \)/u',
    ],
	
	'time' => [
		'enabled' => true,
		'source_timezone' => 'Europe/Kyiv',
		'target_timezone' => 'Europe/Moscow',
		'manual_shift_minutes' => 0, // если понадобится ручная коррекция
	],
	'checks' => [
    'duplicates_db' => [
        'enabled' => true,
        'strict' => true,
    ],
    'duplicates_ms' => [
        'enabled' => false,
        'strict' => false,
    ],
],
];