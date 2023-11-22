<?php

namespace InputMethodEditor;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    
    'form_elements' => [
        'invokables' => [
            # module configuration form
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'inputmethodeditor' => [
        'config' => [
            'inputmethodeditor_elements_to_enable' => "textarea\n[contenteditable]\ninput[type=text]",
            'inputmethodeditor_default_input_method' => null,
            'inputmethodeditor_languages' => null,
        ],
    ],
];
