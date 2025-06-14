<?php

namespace App\Enums;

enum DegreeLevel: string
{
    case BACHELORS_DEGREE = "bachelor degree";
    case MASTERS_DEGREE = "master degree";
    case PHD = "phd";
    case DIPLOMA = "diploma";
    case HIGHSCHOOL = "highschool";

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getLabels(): array
    {
        return [
            self::BACHELORS_DEGREE->value => 'Bachelors Degree',
            self::MASTERS_DEGREE->value => 'Masters Degree',
            self::PHD->value => 'Ph.D.',
            self::DIPLOMA->value => 'Diploma',
            self::HIGHSCHOOL->value => 'High School',
        ];
    }
}
