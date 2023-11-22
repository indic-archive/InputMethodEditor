<?php

declare(strict_types=1);

namespace InputMethodEditor\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init(): void
    {
        $this
            ->add([
                'type' => Element\Textarea::class,
                'name' => 'inputmethodeditor_elements_to_enable',
                'options' => [
                    'label' => 'Input Fields to Enable IME',
                    'info' => 'Provide list of elements to be enabled with the IME support. Please enter one value per line.'
                ],
                'attributes' => [
                    'id' => 'inputmethodeditor_elements_to_enable',
                    // 'required' => true,
                    'rows' => 10,
                ],
            ])
            ->add([
                'type' => Element\Text::class,
                'name' => 'inputmethodeditor_default_input_method',
                'options' => [
                    'label' => 'Default Input Method to Use',
                    'info' => 'Provide machine name of the default input method to use. This will be available directly by typing Ctrl+M on an input field.'
                ],
                'attributes' => [
                    'id' => 'inputmethodeditor_default_input_method',
                ],
            ])
            ->add([
                'type' => Element\Text::class,
                'name' => 'inputmethodeditor_languages',
                'options' => [
                    'label' => 'Input Method Languages',
                    'info' => 'Enter comma separated list of language codes to enable inout methods for each of them. Leave blank to allow all available languages.'
                ],
                'attributes' => [
                    'id' => 'inputmethodeditor_languages',
                ],
            ]);


        // $this->getInputFilter()
        //     ->add([
        //         'name' => 'inputmethodeditor_elements_to_enable',
        //         'required' => true,
        //     ]);
    }
}
