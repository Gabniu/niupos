<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application\Scanner;

enum ScannerInputMode: string
{
    case Barcode = 'barcode';
    case Manual = 'manual';
    case KeyboardWedge = 'keyboard_wedge';
    case Camera = 'camera';
}
