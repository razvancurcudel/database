<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Exception;

/**
 * Is thrown when a foreign key constraint is violated during data modification.
 * 
 * @author Martin Schröder
 */
class ForeignKeyConstraintViolationException extends DatabaseException { }
