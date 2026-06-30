<?php

namespace App\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeImmutableType as BaseDateTimeImmutableType;

class DateTimeImmutableType extends BaseDateTimeImmutableType
{
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeImmutable) {
            // Convertir en UTC et formater en chaîne
            return $value->setTimezone(new \DateTimeZone('UTC'))
                ->format($platform->getDateTimeFormatString());
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', 'DateTimeImmutable']);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        $dateTime = \DateTimeImmutable::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            new \DateTimeZone('UTC')
        );

        if (!$dateTime) {
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                $platform->getDateTimeFormatString()
            );
        }

        return $dateTime->setTimezone(new \DateTimeZone('Europe/Paris'));
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): true
    {
        return true;
    }
}


