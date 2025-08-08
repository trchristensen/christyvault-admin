<?php

namespace App\Enums;

enum UnitOfMeasure: string
{
    case EACH = 'each';
    case PIECE = 'piece';
    case UNIT = 'unit';
    case BUNDLE = 'bundle';
    case DOZEN = 'dozen';
    case PAIR = 'pair';
    case SET = 'set';
    case PACK = 'pack';
    case BOX = 'box';
    case CASE = 'case';
    case POUND = 'pound';
    case KILOGRAM = 'kilogram';
    case OUNCE = 'ounce';
    case GRAM = 'gram';
    case FOOT = 'foot';
    case METER = 'meter';
    case INCH = 'inch';
    case YARD = 'yard';
    case GALLON = 'gallon';
    case LITER = 'liter';
    case QUART = 'quart';
    case PINT = 'pint';
    case SQUARE_FOOT = 'square_foot';
    case SQUARE_METER = 'square_meter';
    case PAIL = 'pail';
    case BAG = 'bag';
    case BOTTLE = 'bottle';
    case TUBE = 'tube';
    case CAN = 'can';
    case CARTON = 'carton';
    case ROLL = 'roll';
    case PACKAGE = 'package';
    case VAULT = 'vault';

    public function label(): string
    {
        return match ($this) {
            self::EACH => 'Each',
            self::PIECE => 'Piece',
            self::UNIT => 'Unit',
            self::BUNDLE => 'Bundle',
            self::DOZEN => 'Dozen',
            self::PAIR => 'Pair',
            self::SET => 'Set',
            self::PACK => 'Pack',
            self::BOX => 'Box',
            self::CASE => 'Case',
            self::POUND => 'Pound (lb)',
            self::KILOGRAM => 'Kilogram (kg)',
            self::OUNCE => 'Ounce (oz)',
            self::GRAM => 'Gram (g)',
            self::FOOT => 'Foot (ft)',
            self::METER => 'Meter (m)',
            self::INCH => 'Inch (in)',
            self::YARD => 'Yard (yd)',
            self::GALLON => 'Gallon (gal)',
            self::LITER => 'Liter (L)',
            self::QUART => 'Quart (qt)',
            self::PINT => 'Pint (pt)',
            self::SQUARE_FOOT => 'Square Foot (sq ft)',
            self::SQUARE_METER => 'Square Meter (sq m)',
            self::PAIL => 'Pail (5 gal)',
            self::BAG => 'Bag',
            self::BOTTLE => 'Bottle',
            self::TUBE => 'Tube',
            self::CAN => 'Can',
            self::CARTON => 'Carton',
            self::ROLL => 'Roll',
            self::PACKAGE => 'Package',
            self::VAULT => 'Vault',
        };
    }

    public function abbreviation(): string
    {
        return match ($this) {
            self::EACH => 'ea',
            self::PIECE => 'pc',
            self::UNIT => 'unit',
            self::BUNDLE => 'bundle',
            self::DOZEN => 'dz',
            self::PAIR => 'pr',
            self::SET => 'set',
            self::PACK => 'pk',
            self::BOX => 'box',
            self::CASE => 'case',
            self::POUND => 'lb',
            self::KILOGRAM => 'kg',
            self::OUNCE => 'oz',
            self::GRAM => 'g',
            self::FOOT => 'ft',
            self::METER => 'm',
            self::INCH => 'in',
            self::YARD => 'yd',
            self::GALLON => 'gal',
            self::LITER => 'L',
            self::QUART => 'qt',
            self::PINT => 'pt',
            self::SQUARE_FOOT => 'sq ft',
            self::SQUARE_METER => 'sq m',
            self::PAIL => 'pail',
            self::BAG => 'bag',
            self::BOTTLE => 'bottle',
            self::TUBE => 'tube',
            self::CAN => 'can',
            self::CARTON => 'ctn',
            self::ROLL => 'rl',
            self::PACKAGE => 'pkg',
            self::VAULT => 'vault',
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}