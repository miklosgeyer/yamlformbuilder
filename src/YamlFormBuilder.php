<?php

namespace MiklosGeyer\YamlFormBuilder;

use MiklosGeyer\YamlFormBuilder\Transformer\AllowEmptyDate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Form\FormInterface;

class YamlFormBuilder extends AbstractType
{

    private $definition;
    //private $options;
    private $form;

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('yamlconfig');
        $resolver->setRequired('use_groups');

        $resolver->setDefaults([
            'validation_groups' => function (FormInterface $form) {
                return $this->resolveValidationGroups($form);
            },
            'use_groups' => false
        ]);

    }

    private function resolveValidationGroups($form){
        $options = $form->getConfig()->getOptions();
        if(!$options['use_groups']){
            return ['Default'];
        }

        $groups = $this->findAllContraintGroups();
        $this->form = $form;
        $this->filterGroups($groups);

        return $groups;
    }

    /**
     * Filtert Constraintgroups, wenn es Bedingungen gibt, dass die constraints nicht angewendet werden sollen.
     * @param $groups
     */
    private function filterGroups(&$groups){
        foreach($this->definition['conditionalConstraints'] as $key => $conditions){
            $logic = key($conditions); # and oder or
            $result = $this->$logic($conditions[$logic]);
            if(!$result){
                # Wenn result == true, wird der Constraint nicht gefiltert
                $groups = array_diff($groups, [$key]);
            }

        }
    }

    private function and($conditions){
        $results = $this->checkConditions($conditions);
        # Wenn kein false gefunden wird, sind alle wahr = AND
        if(in_array(false,$results,true) == true ){
            return false;
        }
        return true;
    }

    private function or($conditions){
        $results = $this->checkConditions($conditions);

        # Wenn ein true gefunden wird -> wahr = OR
        if(in_array(true,$results,true) == true ){
            return true;
        }
        return false;
    }

    private function checkConditions($conditions){
        $results = [];
        foreach($conditions as $item){
            $check = $item['constraint'];
            $checkvalue = $item['value'];
            $formvalue = $this->form->get($item['target'])->getData();
            $results[] = $this->$check($checkvalue,$formvalue);
        }
        return $results;
    }

    /**
     * Nicht leer
     *
     * @param $checkvalue
     * @param $formvalue
     * @return bool
     */
    private function notblank($checkvalue,$formvalue){
        if(is_array($formvalue) && count($formvalue) != 0){
            return true;
        }
        if($formvalue != null && $formvalue != '' && $formvalue != false){
            return true;
        }
        return false;
    }

    /**
     * Genau ein Wert
     * @param $checkvalue
     * @param $formvalue
     * @return bool
     */
    private function equals($checkvalue,$formvalue){
        if(is_array($formvalue)){
            if($checkvalue == $formvalue){
                return true;
            }
            return false;
        }

        if($checkvalue == $formvalue ){
            return true;
        }
        return false;
    }
    /**
     * Checkbox angeklickt
     * @param $checkvalue
     * @param $formvalue
     * @return bool
     */
    private function checked($checkvalue,$formvalue){
        if( $formvalue == true ){
            return true;
        }
        return false;

    }
    /**
     * Checkbox nicht angeklickt
     * @param $checkvalue
     * @param $formvalue
     * @return bool
     */
    private function unchecked($checkvalue,$formvalue){
        if( $formvalue == false ){
            return true;
        }
        return false;

    }


    /**
     * Mehr elemente gewählt als
     * @param $checkvalue
     * @param $formvalue
     * @return bool
     */
    private function more($checkvalue,$formvalue){
        # Array: Anzahl der Elemente
        if(is_array($formvalue)){
            if(count($formvalue)>$checkvalue){
                return true;
            }
            return false;
        }
        return false;

    }

    /**
     * weniger ausgewählt als
     * @param $checkvalue
     * @param $formvalue
     * @return bool
     */
    private function less($checkvalue,$formvalue){
        if(is_array($formvalue)){
            if(count($formvalue) < $checkvalue){
                return true;
            }
            return false;
        }
        return false;
    }
    /**
     * Genau Anzahl Optionen ausgewählt
     * @param $checkvalue
     * @param $formvalue
     * @return bool
     */
    private function exact($checkvalue,$formvalue){
        if(is_array($formvalue)){
            if(count($formvalue) == $checkvalue){
                return true;
            }
            return false;
        }
        return false;
    }
    /**
     * String: länger als
     * @param $checkvalue
     * @param $formvalue
     * @return bool
     */
    private function longer($checkvalue,$formvalue){
        if(is_string($formvalue)){
            if(strlen($formvalue) > $checkvalue){
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * String: kürzer als
     * @param $checkvalue
     * @param $formvalue
     * @return bool
     */
    private function shorter($checkvalue,$formvalue){
        if(is_string($formvalue)){
            if(strlen($formvalue) < $checkvalue){
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * Integer größer als
     * @param $checkvalue
     * @param $formvalue
     * @return bool
     */
    private function bigger($checkvalue,$formvalue){
        if(is_numeric($formvalue)){
            if($formvalue > $checkvalue){
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * Integer kleiner als
     * @param $checkvalue
     * @param $formvalue
     * @return bool
     */
    private function smaller($checkvalue,$formvalue){
        if(is_numeric($formvalue)){
            if($formvalue < $checkvalue){
                return true;
            }
            return false;
        }
        return false;
    }

    /**
     * Holt alle Constraint-Groups aus der Definition
     * @return array
     */
    private function findAllContraintGroups(){
        $groups = [];
        foreach($this->definition['formfields'] as $field){
            if(isset($field['options']['constraints']) ){
                foreach($field['options']['constraints'] as $constraint){
                    if(isset($constraint['groups'])){
                        $groups[] = $constraint['groups'];
                    }
                }
            }
        }
        return $groups;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $yamlconfig = $options['yamlconfig'];
        $this->definition = $this->parseYamlConfig($yamlconfig);

        # Create Fields
        foreach($this->definition['formfields'] as $key => $formfield){
            $formtype = $formfield['fieldtype'];
            $methodName = 'add' . $formtype;
            if(method_exists($this,$methodName)){
                $this->$methodName($builder,$key,$formfield);
            } else {
                $this->add_Type($builder,$key,$formfield);
            }

        }

    }

    private function add_Type($builder,$key,$formfield){
        $classOfType = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\' . $formfield['fieldtype'];

        if(isset($formfield['options']['constraints'])){
            $this->processConstraints($formfield['options']);
        }

        $builder->add($key, $classOfType,
            $formfield['options']
        );
        if(isset($formfield['transformer']) && $formfield['transformer'] != ''){
            $transformerClassName = 'MiklosGeyer\\YamlFormBuilder\\Transformer\\' . $formfield['transformer'];
            $builder->get($key)
                ->addModelTransformer( new $transformerClassName );
        }
    }
    
    private function processConstraints( &$options ){
        $processed = [];
        foreach($options['constraints'] as $constraint => $opts ){
            $class = 'Symfony\\Component\\Validator\\Constraints\\' . $constraint;
            $processed[] = new $class($opts);
        }
        $options['constraints']  = $processed;
    }

    private function parseYamlConfig($yamlconfig){
        try {
            $value = Yaml::parse($yamlconfig);
        } catch (ParseException $exception) {
            #printf('Unable to parse the YAML string: %s', $exception->getMessage());
            # TODO give config with errormessage in Markup
            return ['formfields' => ['error' => ['fieldtype' => 'markup','options' => ['content' =>'<p>Fehler beim Lesen der Konfigurationsdatei: ' . $exception->getMessage() ]]]];
        }
        return $value;
    }
    
    public function getBlockPrefix(){
        return '';
        /*if(isset($this->definition['formid']) && $this->definition['formid'] != null ){
            return $this->definition['formid'];
        }
        return parent::getBlockPrefix();*/
    }
}