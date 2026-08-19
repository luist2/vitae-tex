<?php

return [
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña ingresada no es correcta.',
    'email' => ':Attribute debe ser un email válido.',
    'lowercase' => ':Attribute debe estar en minúsculas.',
    'max' => [
        'array' => ':Attribute no puede contener más de :max elementos.',
        'file' => ':Attribute no puede superar los :max kilobytes.',
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'string' => ':Attribute no puede superar los :max caracteres.',
    ],
    'min' => [
        'array' => ':Attribute debe contener al menos :min elementos.',
        'file' => ':Attribute debe tener al menos :min kilobytes.',
        'numeric' => ':Attribute debe ser al menos :min.',
        'string' => ':Attribute debe tener al menos :min caracteres.',
    ],
    'required' => ':Attribute es obligatorio.',
    'string' => ':Attribute debe ser texto.',
    'unique' => 'Ya existe una cuenta con ese :attribute.',

    'attributes' => [
        'current_password' => 'contraseña actual',
        'email' => 'email',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'title' => 'título',
    ],

    'custom' => [
        'current_password' => [
            'required' => 'La contraseña actual es obligatoria.',
        ],
        'email' => [
            'email' => 'Ingresa un email válido.',
            'required' => 'El email es obligatorio.',
            'unique' => 'Ese email ya está asociado a otra cuenta.',
        ],
        'password' => [
            'required' => 'La contraseña es obligatoria.',
        ],
        'title' => [
            'required' => 'El título es obligatorio.',
        ],
    ],
];
