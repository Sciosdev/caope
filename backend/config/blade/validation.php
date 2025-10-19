<?php

use Stillat\BladeParser\Validation\Validators\ComponentParameterNameSpacingValidator;
use Stillat\BladeParser\Validation\Validators\ComponentShorthandVariableParameterValidator;
use Stillat\BladeParser\Validation\Validators\DebugDirectiveValidator;
use Stillat\BladeParser\Validation\Validators\DirectiveArgumentSpacingValidator;
use Stillat\BladeParser\Validation\Validators\DirectiveArgumentsSpanningLinesValidator;
use Stillat\BladeParser\Validation\Validators\DirectiveSpacingValidator;
use Stillat\BladeParser\Validation\Validators\Documents\InvalidPhpDocumentValidator;
use Stillat\BladeParser\Validation\Validators\DuplicateConditionExpressionsValidator;
use Stillat\BladeParser\Validation\Validators\EmptyConditionValidator;
use Stillat\BladeParser\Validation\Validators\ForElseStructureValidator;
use Stillat\BladeParser\Validation\Validators\InconsistentDirectiveCasingValidator;
use Stillat\BladeParser\Validation\Validators\InconsistentIndentationLevelValidator;
use Stillat\BladeParser\Validation\Validators\NoArgumentsValidator;
use Stillat\BladeParser\Validation\Validators\NodeCompilationValidator;
use Stillat\BladeParser\Validation\Validators\RecursiveIncludeValidator;
use Stillat\BladeParser\Validation\Validators\RequiredArgumentsValidator;
use Stillat\BladeParser\Validation\Validators\RequiresOpenValidator;
use Stillat\BladeParser\Validation\Validators\SwitchValidator;
use Stillat\BladeParser\Validation\Validators\UnpairedConditionValidator;

return [
    'core_validators' => [
        UnpairedConditionValidator::class,
        EmptyConditionValidator::class,
        RequiredArgumentsValidator::class,
        DirectiveArgumentSpacingValidator::class,
        NoArgumentsValidator::class,
        DuplicateConditionExpressionsValidator::class,
        ForElseStructureValidator::class,
        SwitchValidator::class,
        InconsistentDirectiveCasingValidator::class,
        RequiresOpenValidator::class,
        DirectiveSpacingValidator::class,
        DirectiveArgumentsSpanningLinesValidator::class,
        NodeCompilationValidator::class,
        InvalidPhpDocumentValidator::class,
        InconsistentIndentationLevelValidator::class,
        DebugDirectiveValidator::class,
        ComponentParameterNameSpacingValidator::class,
        ComponentShorthandVariableParameterValidator::class,
        RecursiveIncludeValidator::class,
    ],

    'phpstan' => [
        'enabled' => true,
    ],

    'ignore_directives' => [
    ],

    'custom_directives' => [
    ],

    'options' => [
        DirectiveArgumentSpacingValidator::class => [
            'expected_spacing' => 0,
            'ignore_directives' => [],
        ],

        DirectiveSpacingValidator::class => [
            'ignore_directives' => ['selected'],
        ],

        DirectiveArgumentsSpanningLinesValidator::class => [
            'max_line_span' => 5,
            'ignore_directives' => [],
        ],

        DuplicateConditionExpressionsValidator::class => [
            'ignore_directives' => [],
        ],

        EmptyConditionValidator::class => [
            'ignore_directives' => [],
        ],

        NoArgumentsValidator::class => [
            'ignore_directives' => [],
            'custom_directives' => [],
        ],

        RequiredArgumentsValidator::class => [
            'ignore_directives' => [],
            'custom_directives' => [],
        ],

        InconsistentDirectiveCasingValidator::class => [
            'ignore_directives' => [],
            'custom_directives' => [],
        ],

        RequiresOpenValidator::class => [
            'ignore_directives' => [],
            'custom_directives' => [],
        ],

        NodeCompilationValidator::class => [
        ],

        InvalidPhpDocumentValidator::class => [
        ],

        InconsistentIndentationLevelValidator::class => [
            'ignore_directives' => ['section', 'endsection', 'push', 'endpush', 'switch', 'endSwitch', 'forelse', 'endforelse'],
        ],

        DebugDirectiveValidator::class => [
        ],

        ComponentParameterNameSpacingValidator::class => [
        ],

        ComponentShorthandVariableParameterValidator::class => [
        ],

        RecursiveIncludeValidator::class => [
        ],

        SwitchValidator::class => [
        ],

        UnpairedConditionValidator::class => [
            'ignore_directives' => [],
        ],

        ForElseStructureValidator::class => [
        ],
    ],
];
