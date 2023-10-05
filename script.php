<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/upload/City.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/upload/Discount.php';

$csvFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/test.csv';
$city = City::VLADIVOSTOK; // Указать город
$iblockId = 12; // Инфобллок с которым работаем


// Обрабатывает данные из CSV-файла и обновляет инфоблоки
class CsvParser
{
    private $csvFile;
    private $city;
    private $iblockId;

    // Инициализатор
    public function __construct($csvFile, $city, $iblockId)
    {
        $this->csvFile = $csvFile;
        $this->city = $city;
        $this->iblockId = $iblockId;
    }


    // Функция для удаления символов переноса строки
    private function removeNewlines($string)
    {
        return str_replace(array("\r\n", "\r", "\n"), '', $string);
    }

    // Обрабатывает CSV-файл
    public function processCsv()
    {
        if (($handle = fopen($this->csvFile, 'r')) !== false) {
            $keys = [];
            $data = [];

            while (($buffer = fgets($handle)) !== false) {
                $buffer = $this->removeNewlines($buffer);
                $str = explode(';', $buffer);

                if (empty($keys)) {
                    $keys = $str;
                } else {
                    $el = [];
                    foreach ($str as $key => $item) {
                        $el[$keys[$key]] = $item;
                    }

                    $cityKey = 'price_' . $this->city;
                    $el['price'] = $el[$cityKey];

                    $discountPercent = $this->calculateDiscount($el);

                    if ($discountPercent > 0) {
                        $el['discount_price'] = $this->applyDiscount($el['price'], $discountPercent);
                    } else {
                        $el['discount_price'] = $el['price'];
                    }

                    $data[] = $el;
                }
            }

            fclose($handle);

            $this->updateDatabase($data);
        } else {
            echo 'Ошибка открытия файла CSV<br>';
        }
    }

    // Проверяет скидки
    private function calculateDiscount($el)
    {
        $discountPercent = 0;
        if ($el['is_discount'] == 'Y') {
            foreach (Discounts::$discounts as $discountName => $discountDetails) {
                if ($this->city == $discountDetails['city'] || $discountDetails['city'] == 'all') {
                    $discountPercent = $discountDetails['discount_percent'];
                    break;
                }
            }
        }
        return $discountPercent;
    }

    // Расчет скидки
    private function applyDiscount($price, $discountPercent)
    {
        $undiscounted = (float)str_replace('руб', '', $price);
        $discountAmount = $undiscounted * ($discountPercent / 100);
        $discounted = $undiscounted - $discountAmount;
        return $discounted . 'руб';
    }

    // Обновление инфоблоков
    private function updateDatabase($data)
    {
        CModule::IncludeModule('iblock');
        $bs = new CIBlockElement;

        foreach ($data as $key => $el) {
            $xmlId = 'cmt_' . $el['id'];
            $elementId = $this->getElementId($xmlId);

            $updateElement = $this->checkUpdate($elementId, $el);

            if ($updateElement) {
                $arFields = $this->elementFields($el, $xmlId);
                if ($elementId) {
                    if ($bs->Update($elementId, $arFields)) {
                        echo $key . '. Элемент с ID ' . $elementId . ' (XML_ID = ' . $xmlId . ') успешно обновлен<br>';
                    } else {
                        echo $key . '. Ошибка при обновлении элемента: ' . $bs->LAST_ERROR . '<br>';
                    }
                } else {
                    if ($PRODUCT_ID = $bs->Add($arFields)) {
                        echo $key . '. Новый элемент с ID ' . $PRODUCT_ID . ' (XML_ID = ' . $xmlId . ') успешно добавлен<br>';
                    } else {
                        echo $key . '. Ошибка при добавлении элемента: ' . $bs->LAST_ERROR . '<br>';
                    }
                }
            }
        }
    }

    // Проверяет, существует ли элемент с таким XML_ID
    private function getElementId($xmlId)
    {
        $elementId = false;
        $res = CIBlockElement::GetList(array(), array(
            'IBLOCK_ID' => $this->iblockId,
            '=XML_ID' => $xmlId,
        ), false, false, array('ID'));

        if ($ob = $res->Fetch()) {
            $elementId = $ob['ID'];
        }
        return $elementId;
    }

    // Проверяет, были ли изменения в CSV-файле
    private function checkUpdate($elementId, $el)
    {
        if ($elementId) {
            $currentProps = CIBlockElement::GetProperty($this->iblockId, $elementId, array(), array());

            while ($prop = $currentProps->Fetch()) {
                if ($el[$prop['CODE']] != $prop['VALUE']) {
                    return true;
                }
            }
        } else {
            return true;
        }
        return false;
    }

    // Задает параметры и свойства
    private function elementFields($el, $xmlId)
    {
        $PROP = array(
            'ID' => $el['id'],
            'NAME' => $el['name'],
            'PREVIEW_TEXT' => $el['preview_text'],
            'DETAIL_TEXT' => $el['detail_text'],
            'PROP1' => $el['prop1'],
            'PROP2' => $el['prop2'],
            'PRICE' => $el['price'],
            'DISCOUNT_PRICE' => $el['discount_price'],
        );

        return array(
            'ACTIVE' => 'Y',
            'IBLOCK_ID' => $this->iblockId,
            'NAME' => 'product_' . $el['id'],
            'XML_ID' => $xmlId,
            'PROPERTY_VALUES' => $PROP,
        );
    }
}

$сsvParser = new CsvParser($csvFile, $city, $iblockId);
$сsvParser->processCsv();

