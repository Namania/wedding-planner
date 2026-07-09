<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Wedding Planner API',
    description: 'API de gestion pour la préparation du mariage.'
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Serveur API'
)]
abstract class Controller
{
    //
}
