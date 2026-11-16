<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Admission refusée (pas candidat, pas de décision « admis », pas d'année en cours...). Le message est montré tel quel.
 */
class AdmissionImpossible extends RuntimeException {}
