<?php

declare(strict_types=1);

return [
    'reset' => 'Contraseña actualizada correctamente.',
    'sent' => 'Se ha enviado un código de recuperación a tu correo.',
    'throttled' => 'Por favor espere antes de intentar de nuevo.',
    'token' => 'El token de restablecimiento de contraseña es inválido.',
    'user' => 'No encontramos ningún usuario con ese correo electrónico.',
    'code_sent' => 'Se ha enviado un código de recuperación a tu correo.',
    'invalid_code' => 'El código es inválido o ha expirado.',
    'incorrect_code' => 'El código ingresado es incorrecto.',
    'code_expired' => 'El código ha expirado. Solicita uno nuevo.',

    // Email Template Translations
    'mail_subject' => 'Código de recuperación de contraseña',
    'mail_header_subtitle' => 'Seguridad de la Cuenta',
    'mail_greeting' => 'Hola, :name:',
    'mail_greeting_general' => 'Hola:',
    'mail_intro' => 'Recibimos una solicitud para restablecer la contraseña de tu cuenta en :app. Utiliza el siguiente código de verificación de 6 dígitos para completar el proceso:',
    'mail_code_label' => 'Tu código de seguridad',
    'mail_expiration' => 'Este código expirará en <strong>:minutes minutos</strong>. Si no lo utilizas dentro de este tiempo, deberás solicitar uno nuevo.',
    'mail_security_title' => '¿No solicitaste este cambio?',
    'mail_security_text' => 'Si no realizaste esta solicitud, puedes ignorar este correo de forma segura. Tu contraseña actual permanecerá sin cambios.',
    'mail_footer_automated' => 'Este es un correo automático generado por el sistema. Por favor, no respondas a este mensaje.',
    'mail_all_rights_reserved' => 'Todos los derechos reservados.',
];
