Papir ERP
MoySklad Attributes Mapping

Источник (MS) | Code (auto) | name_main (ERP)

cashin
| Источник    | Code           | name_main   |
| ----------- | -------------- | ----------- |
| Перемещение | peremeshchenie | is_transfer |

cashout
| Источник    | Code           | name_main   |
| ----------- | -------------- | ----------- |
| Перемещение | peremeshchenie | is_transfer |

customerorder
| Источник           | Code              | name_main            |
| ------------------ | ----------------- | -------------------- |
| ТТН                | ttn               | tracking_number      |
| Статус ТТН         | status_ttn        | tracking_status      |
| Стоимость доставки | stoimost_dostavki | shipping_cost        |
| Кто платит         | kto_platit        | shipping_payer       |
| Способ доставки    | sposob_dostavki   | shipping_method      |
| Адрес доставки     | adres_dostavki    | shipping_address     |
| Способ оплаты      | sposob_oplaty     | payment_method       |
| Город              | gorod             | shipping_city        |
| Отделение          | otdelenie         | shipping_branch      |
| Отделение (строка) | otdelenie_stroka  | shipping_branch_name |
| Тип доставки       | tip_dostavki      | delivery_type        |
| Вес                | ves               | weight               |
| Адрес по IP        | adres_po_ip       | ip_address           |

demand
| Источник                | Code                     | name_main               |
| ----------------------- | ------------------------ | ----------------------- |
| Створити ТТН            | stvoriti_ttn             | create_tracking         |
| ТТН                     | ttn                      | tracking_number         |
| Перевізник              | pereviznik               | carrier                 |
| Оплата                  | oplata                   | payment_term            |
| Вага                    | vaga                     | weight                  |
| Кількість місць         | kilkist_mists            | package_count           |
| Довжина                 | dovzhina                 | length                  |
| Ширина                  | shirina                  | width                   |
| Висота                  | visota                   | height                  |
| Місто відправки         | misto_vidpravki          | shipping_city_store     |
| Опис по місцям          | opis_po_mistsyam         | package_description     |
| Відправник              | vidpravnik               | sender                  |
| Контакт відправника     | kontakt_vidpravnika      | sender_contact          |
| Місто                   | misto                    | recipient_city          |
| Відділення відправник   | viddilennya_vidpravnik   | sender_branch           |
| Тип доставки            | tip_dostavki             | delivery_type           |
| Платник доставки        | platnik_dostavki         | shipping_payer          |
| Відділення отримувач    | viddilennya_otrimuvach   | recipient_branch        |
| Вулиця                  | vulitsya                 | street                  |
| Дім                     | dim                      | building                |
| Форма оплати            | forma_oplati             | payment_form            |
| Контроль оплати         | kontrol_oplati           | payment_control         |
| Опис в ТТН              | opis_v_ttn               | tracking_description    |
| Квартира                | kvartira                 | apartment               |
| Доп.расходы             | dop_raskhody             | extra_cost              |
| Дата відгрузки          | data_vidgruzki           | shipped_at              |
| Сповіщення НП           | spovishchennya_np        | nova_poshta_notice      |
| Етикетка                | etiketka                 | label_url               |
| Оновити                 | onoviti                  | refresh_link            |
| Повідомлення            | povidomlennya            | notification_text       |
| відправити повідомлення | vidpraviti_povidomlennya | send_notification       |
| ЄДРПОУ Доставки         | yedrpou_dostavki         | delivery_company_tax_id |
| Організація отримувач   | organizatsiya_otrimuvach | recipient_company       |
| Контакт доставка        | kontakt_dostavka         | delivery_contact        |
| Телефон контакта        | telefon_kontakta         | contact_phone           |

move
| Источник                | Code                           | name_main             |
| ----------------------- | ------------------------------ | --------------------- |
| Время входящего платежа | vremya_vkhodyashchego_platezha | payment_received_time |
| Перемещение             | peremeshchenie                 | is_transfer           |

paymentin
| Источник                | Code                           | name_main             |
| ----------------------- | ------------------------------ | --------------------- |
| Время входящего платежа | vremya_vkhodyashchego_platezha | payment_received_time |
| Перемещение             | peremeshchenie                 | is_transfer           |

paymentout
| Источник                 | Code                            | name_main         |
| ------------------------ | ------------------------------- | ----------------- |
| Время исходящего платежа | vremya_iskhodyashchego_platezha | payment_sent_time |
| Перемещение              | peremeshchenie                  | is_transfer       |

purchaseorder
| Источник    | Code        | name_main       |
| ----------- | ----------- | --------------- |
| ТТН         | ttn         | tracking_number |
| Статус      | status      | tracking_status |
| Перевіренно | perevirenno | is_verified     |
