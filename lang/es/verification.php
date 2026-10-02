<?php

declare(strict_types=1);

return [
    'code_sent' => 'Te enviamos un código de verificación a tu correo.',
    'verified' => 'Correo verificado correctamente.',
    'incorrect_code' => 'El código ingresado es incorrecto.',
    'code_expired' => 'El código de verificación ha expirado. Solicita uno nuevo.',
    'too_many_attempts' => 'Demasiados intentos incorrectos. Por favor solicita un nuevo código.',
    'not_verified' => 'Debes verificar tu correo antes de iniciar sesión.',
    'already_verified' => 'Tu correo ya está verificado.',

    // Email Template Translations
    'mail_subject' => 'Código de verificación de correo',
    'mail_header_subtitle' => 'Verificación de correo',
    'mail_greeting' => 'Hola, :name:',
    'mail_greeting_general' => 'Hola:',
    'mail_intro' => '¡Bienvenido a :app! Usa el siguiente código de 6 dígitos para verificar tu dirección de correo:',
    'mail_code_label' => 'Tu código de verificación',
    'mail_expiration' => 'Este código expirará en <strong>:minutes minutos</strong>. Si no lo utilizas dentro de este tiempo, deberás solicitar uno nuevo.',
    'mail_security_title' => '¿No creaste una cuenta?',
    'mail_security_text' => 'Si no creaste una cuenta, puedes ignorar este correo de forma segura.',
    'mail_footer_automated' => 'Este es un correo automático generado por el sistema. Por favor, no respondas a este mensaje.',
    'mail_all_rights_reserved' => 'Todos los derechos reservados.',
];
