<?php 

function tarhan_validate_essay_text($text, $max_length)
{
    $text = trim($text);
    $len_str = mb_strlen($text, 'UTF-8');
    if($len_str===0){
        $error = "Введите текст эссе";
        return array(
            "text" => $text,
            "error" => $error,
            "length" => $len_str,
            
        );
    }
    elseif($len_str>$max_length){
        $error = "Текст превышает допустимую длину";
        return array(
            "text" => $text,
            "error" => $error,
            "length" => $len_str,
            
        );
    }else{
        $error = null;
        return array(
            "text" => $text,
            "error" => $error,
            "length" => $len_str,
            
        );
    }
}