<?php

namespace MiklosGeyer\YamlFormBuilder\Transformer;

use Symfony\Component\Form\DataTransformerInterface;

class AllowEmptyDate implements DataTransformerInterface
{
    public function transform($value){

        if($value == '0000-00-00' || $value == null){
            return '';
        }
        return $value;
    }

    public function reverseTransform($value){
        if($value == ''){
            return '0000-00-00';
        }
        return $value;
    }
}