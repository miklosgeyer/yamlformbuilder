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

class YamlFormBuilder extends AbstractType
{

    private $definition;

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('yamlconfig');

    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $yamlconfig = $options['yamlconfig'];
        $this->definition = $this->parseYamlConfig($yamlconfig);

        # Create Filds
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