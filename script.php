<?php

require_once 
$_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// Путь к CSV-файлу
$csvFile = $_SERVER['DOCUMENT_ROOT'] . '/upload/test.csv';

// Функция для удаления символов переноса строки
function removeNewlines($string)
{
    return str_replace(array("\r\n", "\r", "\n"), '', $string);
}

// Открываем CSV-файл
if (($handle = fopen($csvFile, 'r')) !== false) {
    $keys = array();
    $data = array();
    
    while (($buffer = fgets($handle)) !== false) {
        $buffer = removeNewlines($buffer);
        $str = explode(';', $buffer);
        
        if (empty($keys)) {
            $keys = $str;
        } else {
            $el = array();
            foreach ($str as $key => $item) {
                $el[$keys[$key]] = $item;
            }
            $data[] = $el;
        }
    }

    fclose($handle);

    CModule::IncludeModule('iblock');

    foreach ($data as $key => $el) {
        $iblockId = 12; // Укажите ID вашего инфоблока
        
        // Проверяем, существует ли элемент с таким XML_ID
        $xmlId = 'product_' . $el['id'];
        $elementId = false;
        $res = CIBlockElement::GetList(array(), array(
            'IBLOCK_ID' => $iblockId,
            '=XML_ID' => $xmlId,
        ), false, false, array('ID'));
        
        if ($ob = $res->Fetch()) {
            $elementId = $ob['ID'];
        }
        
        $bs = new CIBlockElement;

        // Проверяем, были ли изменения в CSV-файле
        $updateElement = false;
        if ($elementId) {
            // Получаем текущие значения элемента
            $currentProps = CIBlockElement::GetProperty($iblockId, $elementId, array(), array());
            
            while ($prop = $currentProps->Fetch()) {
                if ($el[$prop['CODE']] != $prop['VALUE']) {
                    $updateElement = true;
                    break;
                }
            }
        } else {
            $updateElement = true; // Элемент не существует, создаем новый
        }
        
        if ($updateElement) {
            $PROP = array(
                'ID' => $el['id'],
                'NAME' => $el['name'],
                'PREVIEW_TEXT' => $el['preview_text'],
                'DETAIL_TEXT' => $el['detail_text'],
                'PROP1' => $el['prop1'],
                'PROP2' => $el['prop2'],
            );
            
            $arFields = array(
                'ACTIVE' => 'Y',
                'IBLOCK_ID' => $iblockId,
                'NAME' => 'product_' . $el['id'],
                'XML_ID' => $xmlId,
                'PROPERTY_VALUES' => $PROP,
            );
            
            if ($elementId) {
                // Обновляем существующий элемент
                if ($bs->Update($elementId, $arFields)) {
                    echo $key . '. Элемент с ID ' . $elementId . ' (XML_ID = ' . $xmlId . ') успешно обновлен<br>';
                } else {
                    echo $key . '. Ошибка при обновлении элемента: ' . $bs->LAST_ERROR . '<br>';
                }
            } else {
                // Создаем новый элемент
                if ($PRODUCT_ID = $bs->Add($arFields)) {
                    echo $key . '. Новый элемент с ID ' . $PRODUCT_ID . ' (XML_ID = ' . $xmlId . ') успешно добавлен<br>';
                } else {
                    echo $key . '. Ошибка при добавлении элемента: ' . $bs->LAST_ERROR . '<br>';
                }
            }
        } 
    }
} else {
    echo 'Ошибка открытия файла CSV<br>';
}

