<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un acte ne peut pas être délivré (inscription absente, dérogation sans décision...). Le message est montré tel quel.
 */
class ActeImpossible extends RuntimeException {}
