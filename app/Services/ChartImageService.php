<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ChartImageService
{

    /**
     * Generar gráfico de barras con leyenda horizontal debajo
     */
    public function generateBarChart(array $labels, array $incomeData, array $expenseData, string $title = ''): string
    {
        $width = 800;
        $height = 400;
        $padding = 60;
        $barWidth = 30;
        $gap = 15;
        $chartHeight = $height - $padding * 2;

        // ✅ Aumentar altura para la leyenda
        $legendHeight = 40;
        $totalHeight = $height + $legendHeight;

        // Crear imagen
        $image = imagecreatetruecolor($width, $totalHeight);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $green = imagecolorallocate($image, 16, 185, 129);
        $red = imagecolorallocate($image, 239, 68, 68);
        $gray = imagecolorallocate($image, 200, 200, 200);
        $darkGray = imagecolorallocate($image, 100, 100, 100);

        imagefill($image, 0, 0, $white);

        // Calcular valores máximos
        $maxValue = max(array_merge($incomeData, $expenseData));
        $maxValue = $maxValue > 0 ? $maxValue : 1;

        // ✅ Título
        if ($title) {
            $titleX = ($width - strlen($title) * 8) / 2;
            imagestring($image, 5, $titleX, 10, $title, $darkGray);
        }

        // ✅ Dibujar ejes
        imageline($image, $padding, $padding, $padding, $height - $padding, $black);
        imageline($image, $padding, $height - $padding, $width - $padding, $height - $padding, $black);

        // ✅ Dibujar líneas de la cuadrícula
        $gridLines = 5;
        for ($i = 0; $i <= $gridLines; $i++) {
            $y = $height - $padding - ($i / $gridLines) * $chartHeight;
            $value = round(($i / $gridLines) * $maxValue, 2);
            $labelX = 5;
            $labelY = $y - 5;
            imagestring($image, 2, $labelX, $labelY, number_format($value, 0), $darkGray);
            imageline($image, $padding - 5, $y, $width - $padding, $y, $gray);
        }

        // ✅ Dibujar barras
        $totalBars = count($labels) * 2;
        $totalWidth = $totalBars * ($barWidth + $gap);
        $startX = ($width - $padding * 2 - $totalWidth) / 2 + $padding;

        foreach ($labels as $index => $label) {
            $xBase = $startX + $index * ($barWidth * 2 + $gap * 2);

            // Barra de ingresos
            $incomeHeight = ($incomeData[$index] / $maxValue) * $chartHeight;
            $yIncome = $height - $padding - $incomeHeight;
            imagefilledrectangle($image, $xBase, $yIncome, $xBase + $barWidth, $height - $padding, $green);

            // Etiqueta del eje X (mes)
            $labelX = $xBase + $barWidth / 2 - 10;
            $labelY = $height - $padding + 5;
            $shortLabel = strlen($label) > 8 ? substr($label, 0, 6) . '...' : $label;
            imagestring($image, 2, $labelX, $labelY, $shortLabel, $darkGray);

            // Valor encima de la barra de ingresos
            if ($incomeData[$index] > 0) {
                $valueX = $xBase + $barWidth / 2 - 15;
                $valueY = $yIncome - 15;
                $valueStr = number_format($incomeData[$index], 0);
                // Ajustar posición según longitud
                $valueStrLen = strlen($valueStr);
                $valueX = $xBase + $barWidth / 2 - ($valueStrLen * 3);
                imagestring($image, 2, $valueX, $valueY, $valueStr, $green);
            }

            // Barra de egresos
            $xExpense = $xBase + $barWidth + $gap;
            $expenseHeight = ($expenseData[$index] / $maxValue) * $chartHeight;
            $yExpense = $height - $padding - $expenseHeight;
            imagefilledrectangle($image, $xExpense, $yExpense, $xExpense + $barWidth, $height - $padding, $red);

            // Valor encima de la barra de egresos
            if ($expenseData[$index] > 0) {
                $valueX2 = $xExpense + $barWidth / 2 - 15;
                $valueY2 = $yExpense - 15;
                $valueStr = number_format($expenseData[$index], 0);
                $valueStrLen = strlen($valueStr);
                $valueX2 = $xExpense + $barWidth / 2 - ($valueStrLen * 3);
                imagestring($image, 2, $valueX2, $valueY2, $valueStr, $red);
            }
        }

        // ✅ Dibujar leyenda horizontal debajo del gráfico
        $this->drawBarLegend($image, $width, $height, $green, $red, $darkGray);

        // Guardar imagen
        $path = storage_path("app/public/charts/bar_chart_{$title}.png");
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * Dibujar leyenda horizontal para gráfico de barras
     */
    private function drawBarLegend($image, int $width, int $chartHeight, $green, $red, $darkGray): void
    {
        $legendY = $chartHeight + 20;
        $itemWidth = 100;
        $spacing = 30;
        $totalWidth = 2 * $itemWidth + $spacing;
        $startX = ($width - $totalWidth) / 2;

        // Fondo de la leyenda
        $legendBg = imagecolorallocate($image, 248, 250, 252);
        imagefilledrectangle($image, $startX - 10, $legendY - 8, $startX + $totalWidth + 10, $legendY + 22, $legendBg);
        imagerectangle($image, $startX - 10, $legendY - 8, $startX + $totalWidth + 10, $legendY + 22, $darkGray);

        // Ingresos
        $x1 = $startX;
        imagefilledrectangle($image, $x1, $legendY, $x1 + 15, $legendY + 10, $green);
        imagestring($image, 2, $x1 + 20, $legendY - 2, 'Ingresos', $darkGray);

        // Egresos
        $x2 = $x1 + $itemWidth + $spacing;
        imagefilledrectangle($image, $x2, $legendY, $x2 + 15, $legendY + 10, $red);
        imagestring($image, 2, $x2 + 20, $legendY - 2, 'Egresos', $darkGray);
    }

    /**
     * Generar gráfico de torta
     */
    public function generatePieChart(array $labels, array $values, string $colors = null): string
    {
        $width = 600;
        $height = 400;
        $centerX = 150;
        $centerY = 200;
        $radius = 120;

        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $darkGray = imagecolorallocate($image, 50, 50, 50);
        imagefill($image, 0, 0, $white);

        $total = array_sum($values);
        if ($total == 0) {
            // No hay datos
            imagestring($image, 3, 50, 180, 'No hay datos para mostrar', $darkGray);
            $path = storage_path("app/public/charts/pie_chart_empty.png");
            imagepng($image, $path);
            imagedestroy($image);
            return $path;
        }

        // Colores predefinidos
        $colorPalette = [
            [52, 152, 219],  // Azul
            [46, 204, 113],  // Verde
            [241, 196, 15],  // Amarillo
            [231, 76, 60],   // Rojo
            [155, 89, 182],  // Morado
            [52, 73, 94],    // Gris oscuro
            [26, 188, 156],  // Turquesa
            [243, 156, 18],  // Naranja
            [192, 57, 43],   // Rojo oscuro
            [41, 128, 185],  // Azul oscuro
        ];

        $startAngle = 0;
        $sliceIndex = 0;

        foreach ($values as $index => $value) {
            $percentage = $value / $total;
            $angle = $percentage * 360;

            if ($angle > 0) {
                $colorIndex = $sliceIndex % count($colorPalette);
                $color = $colorPalette[$colorIndex];
                $sliceColor = imagecolorallocate($image, $color[0], $color[1], $color[2]);

                imagefilledarc($image, $centerX, $centerY, $radius * 2, $radius * 2, $startAngle, $startAngle + $angle, $sliceColor, IMG_ARC_PIE);

                // Guardar el ángulo medio para la etiqueta
                $midAngle = $startAngle + $angle / 2;
                $labelX = $centerX + ($radius * 0.7) * cos(deg2rad($midAngle));
                $labelY = $centerY + ($radius * 0.7) * sin(deg2rad($midAngle));
                imagestring($image, 2, $labelX, $labelY, round($percentage * 100) . '%', $white);

                $startAngle += $angle;
                $sliceIndex++;
            }
        }

        // ✅ Leyenda a la derecha
        $legendX = 280;
        $legendY = 30;
        $sliceIndex = 0;

        foreach ($labels as $index => $label) {
            if ($values[$index] > 0) {
                $colorIndex = $sliceIndex % count($colorPalette);
                $color = $colorPalette[$colorIndex];
                $boxColor = imagecolorallocate($image, $color[0], $color[1], $color[2]);

                $yPos = $legendY + $sliceIndex * 25;
                imagefilledrectangle($image, $legendX, $yPos, $legendX + 20, $yPos + 15, $boxColor);
                $shortLabel = strlen($label) > 15 ? substr($label, 0, 12) . '...' : $label;
                imagestring($image, 3, $legendX + 28, $yPos, $shortLabel, $darkGray);

                // Porcentaje
                $percentage = $values[$index] / $total;
                $percentStr = number_format($percentage * 100, 1) . '%';
                imagestring($image, 2, $legendX + 28, $yPos + 18, $percentStr, $darkGray);

                $sliceIndex++;
            }
        }

        $path = storage_path("app/public/charts/pie_chart.png");
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * Generar gráfico de líneas para el PDF
     * 
     * @param array $labels Etiquetas del eje X (ej: meses)
     * @param array $incomeData Datos de ingresos
     * @param array $expenseData Datos de egresos
     * @param array $netData Datos de flujo neto
     * @param string $title Título del gráfico
     * @param int $width Ancho de la imagen
     * @param int $height Alto de la imagen
     * @return string|null Ruta de la imagen generada o null si no hay datos
     */
    public function generateLineChart(
        array $labels,
        array $incomeData,
        array $expenseData,
        array $netData,
        string $title = 'Evolucion Mensual',
        int $width = 700,
        int $height = 300
    ): ?string {
        // Validar que existan datos
        if (empty($labels) || empty($incomeData)) {
            return null;
        }

        // ✅ Si solo hay un punto de datos, mostrar una barra simple
        if (count($labels) === 1) {
            return $this->generateSinglePointChart($labels, $incomeData, $expenseData, $netData, $title, $width, $height);
        }

        // ✅ Aumentar altura para la leyenda debajo
        $legendHeight = 40;
        $totalHeight = $height + $legendHeight;


        $padding = 50;
        $chartHeight = $height - $padding * 2;
        $chartWidth = $width - $padding * 2;

        // Crear imagen
        $image = imagecreatetruecolor($width, $height);

        // Colores
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $gray = imagecolorallocate($image, 200, 200, 200);
        $darkGray = imagecolorallocate($image, 100, 100, 100);
        $green = imagecolorallocate($image, 16, 185, 129);
        $red = imagecolorallocate($image, 239, 68, 68);
        $blue = imagecolorallocate($image, 79, 70, 229);
        $lightGreen = imagecolorallocate($image, 209, 250, 229);
        $lightRed = imagecolorallocate($image, 254, 226, 226);
        $lightBlue = imagecolorallocate($image, 224, 231, 255);

        // Fondo blanco
        imagefill($image, 0, 0, $white);

        // Calcular valores máximos para escalar correctamente
        $allValues = array_merge($incomeData, $expenseData, $netData);
        $maxValue = max($allValues);
        $maxValue = $maxValue > 0 ? $maxValue : 1;

        // Dibujar ejes
        imageline($image, $padding, $padding, $padding, $height - $padding, $black);
        imageline($image, $padding, $height - $padding, $width - $padding, $height - $padding, $black);

        // Dibujar grid horizontal
        $gridLines = 5;
        for ($i = 0; $i <= $gridLines; $i++) {
            $y = $height - $padding - ($i / $gridLines) * $chartHeight;
            $value = round(($i / $gridLines) * $maxValue, 0);

            // Línea de grid
            imageline($image, $padding - 5, $y, $width - $padding, $y, $gray);

            // Etiqueta de valor (formateada sin decimales)
            $label = number_format($value, 0);
            $labelWidth = strlen($label) * 6; // Aproximación del ancho
            $labelX = $padding - $labelWidth - 8;
            $labelY = $y - 5;
            imagestring($image, 2, $labelX, $labelY, $label, $darkGray);
        }

        // Dibujar líneas si hay más de 1 punto
        if (count($labels) > 1) {
            // Línea de ingresos (verde)
            $this->drawLineOnChart(
                $image,
                $incomeData,
                $maxValue,
                $padding,
                $chartHeight,
                $chartWidth,
                $green,
                $lightGreen,
                3,
                true // con área rellena
            );

            // Línea de egresos (rojo)
            $this->drawLineOnChart(
                $image,
                $expenseData,
                $maxValue,
                $padding,
                $chartHeight,
                $chartWidth,
                $red,
                $lightRed,
                3,
                true // con área rellena
            );

            // Línea de flujo neto (azul, sin área)
            $this->drawLineOnChart(
                $image,
                $netData,
                $maxValue,
                $padding,
                $chartHeight,
                $chartWidth,
                $blue,
                null,
                2,
                false // sin área rellena
            );
        } else {
            // Si solo hay un punto, mostrar barras individuales
            $this->drawSinglePoints(
                $image,
                $incomeData,
                $expenseData,
                $netData,
                $maxValue,
                $padding,
                $chartHeight,
                $chartWidth,
                $green,
                $red,
                $blue
            );
        }

        // Etiquetas del eje X
        $step = $chartWidth / (count($labels) - 1);
        foreach ($labels as $index => $label) {
            $x = $padding + $index * $step;
            // Acortar etiquetas largas
            $shortLabel = strlen($label) > 12 ? substr($label, 0, 10) . '..' : $label;
            $labelWidth = strlen($shortLabel) * 5;
            $labelX = $x - $labelWidth / 2;
            $labelY = $height - $padding + 5;
            imagestring($image, 2, $labelX, $labelY, $shortLabel, $darkGray);
        }

        // Título
        if ($title) {
            $titleX = ($width - strlen($title) * 7) / 2;
            imagestring($image, 5, $titleX, 8, $title, $darkGray);
        }

        // Leyenda
        $this->drawLegendHorizontal($image, $width, $height, $green, $red, $blue, $darkGray);

        // Guardar imagen
        $filename = 'line_chart_' . uniqid() . '.png';
        $path = storage_path("app/temp/charts/{$filename}");
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * Generar gráfico para un solo punto de datos
     */
    private function generateSinglePointChart(
        array $labels,
        array $incomeData,
        array $expenseData,
        array $netData,
        string $title,
        int $width,
        int $height
    ): ?string {
        $padding = 50;
        $chartHeight = $height - $padding * 2;
        $chartWidth = $width - $padding * 2;

        $image = imagecreatetruecolor($width, $height);

        // Colores
        $white = imagecolorallocate($image, 255, 255, 255);
        $darkGray = imagecolorallocate($image, 100, 100, 100);
        $green = imagecolorallocate($image, 16, 185, 129);
        $red = imagecolorallocate($image, 239, 68, 68);
        $blue = imagecolorallocate($image, 79, 70, 229);

        imagefill($image, 0, 0, $white);

        // Calcular valores máximos
        $allValues = array_merge($incomeData, $expenseData, $netData);
        $maxValue = max($allValues);
        $maxValue = $maxValue > 0 ? $maxValue : 1;

        // Dibujar ejes
        imageline($image, $padding, $padding, $padding, $height - $padding, $darkGray);
        imageline($image, $padding, $height - $padding, $width - $padding, $height - $padding, $darkGray);

        // Dibujar barras para cada dato
        $totalBars = count($incomeData) + count($expenseData) + count($netData);
        $barWidth = 40;
        $gap = 20;
        $totalWidth = $totalBars * ($barWidth + $gap);
        $startX = ($width - $padding * 2 - $totalWidth) / 2 + $padding;

        // Ingresos
        $incomeHeight = ($incomeData[0] / $maxValue) * $chartHeight;
        $yIncome = $height - $padding - $incomeHeight;
        imagefilledrectangle($image, $startX, $yIncome, $startX + $barWidth, $height - $padding, $green);
        imagestring($image, 2, $startX + $barWidth / 2 - 15, $yIncome - 15, number_format($incomeData[0], 0), $green);

        // Egresos
        $xExpense = $startX + $barWidth + $gap;
        $expenseHeight = ($expenseData[0] / $maxValue) * $chartHeight;
        $yExpense = $height - $padding - $expenseHeight;
        imagefilledrectangle($image, $xExpense, $yExpense, $xExpense + $barWidth, $height - $padding, $red);
        imagestring($image, 2, $xExpense + $barWidth / 2 - 15, $yExpense - 15, number_format($expenseData[0], 0), $red);

        // Flujo Neto
        $xNet = $xExpense + $barWidth + $gap;
        $netHeight = ($netData[0] / $maxValue) * $chartHeight;
        $yNet = $height - $padding - $netHeight;
        imagefilledrectangle($image, $xNet, $yNet, $xNet + $barWidth, $height - $padding, $blue);
        imagestring($image, 2, $xNet + $barWidth / 2 - 15, $yNet - 15, number_format($netData[0], 0), $blue);

        // Etiquetas del eje X
        $labelPositions = [$startX, $xExpense, $xNet];
        $labelTexts = ['Ingresos', 'Egresos', 'Flujo Neto'];
        foreach ($labelPositions as $index => $x) {
            imagestring($image, 2, $x + $barWidth / 2 - 15, $height - $padding + 5, $labelTexts[$index], $darkGray);
        }

        // Título
        if ($title) {
            $titleX = ($width - strlen($title) * 7) / 2;
            imagestring($image, 5, $titleX, 8, $title, $darkGray);
        }

        // Guardar imagen
        $filename = 'single_chart_' . uniqid() . '.png';
        $path = storage_path("app/temp/charts/{$filename}");
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * Dibujar una línea en el gráfico
     */
    private function drawLineOnChart(
        $image,
        array $data,
        float $maxValue,
        int $padding,
        int $chartHeight,
        int $chartWidth,
        $lineColor,
        $fillColor = null,
        int $lineWidth = 2,
        bool $fillArea = false
    ): void {
        $count = count($data);
        if ($count < 2) {
            return;
        }

        $step = $chartWidth / ($count - 1);
        $points = [];

        // Calcular puntos
        foreach ($data as $index => $value) {
            $x = $padding + $index * $step;
            $y = $padding + $chartHeight - ($value / $maxValue) * $chartHeight;
            $points[] = ['x' => $x, 'y' => $y];
        }

        // Rellenar área debajo de la línea
        if ($fillArea && $fillColor) {
            $polygonPoints = [];
            // Punto inicial (esquina inferior izquierda)
            $polygonPoints[] = $points[0]['x'];
            $polygonPoints[] = $padding + $chartHeight;

            // Todos los puntos de la línea
            foreach ($points as $point) {
                $polygonPoints[] = $point['x'];
                $polygonPoints[] = $point['y'];
            }

            // Punto final (esquina inferior derecha)
            $polygonPoints[] = $points[count($points) - 1]['x'];
            $polygonPoints[] = $padding + $chartHeight;

            imagefilledpolygon($image, $polygonPoints, count($polygonPoints) / 2, $fillColor);
        }

        // Dibujar líneas entre puntos
        for ($i = 0; $i < count($points) - 1; $i++) {
            // Línea con grosor
            for ($j = 0; $j < $lineWidth; $j++) {
                imageline(
                    $image,
                    $points[$i]['x'],
                    $points[$i]['y'] + $j,
                    $points[$i + 1]['x'],
                    $points[$i + 1]['y'] + $j,
                    $lineColor
                );
            }
        }

        // Dibujar puntos
        foreach ($points as $point) {
            // Círculo exterior (brillo)
            imagefilledellipse($image, $point['x'], $point['y'], 8, 8, $lineColor);
            // Círculo interior (blanco)
            imagefilledellipse($image, $point['x'], $point['y'], 4, 4, imagecolorallocate($image, 255, 255, 255));
        }
    }

    /**
     * Dibujar puntos individuales cuando solo hay un dato
     */
    private function drawSinglePoints(
        $image,
        array $incomeData,
        array $expenseData,
        array $netData,
        float $maxValue,
        int $padding,
        int $chartHeight,
        int $chartWidth,
        $green,
        $red,
        $blue
    ): void {
        // Si solo hay un punto, dibujar barras individuales
        $count = count($incomeData);
        if ($count === 0) {
            return;
        }

        // Usar un ancho de barra razonable
        $barWidth = min(40, $chartWidth / ($count * 3));
        $gap = $barWidth / 2;

        foreach ($incomeData as $index => $value) {
            $x = $padding + ($index / max(1, $count - 1)) * $chartWidth;

            // Ingreso
            $height = ($value / $maxValue) * $chartHeight;
            if ($height > 0) {
                imagefilledrectangle(
                    $image,
                    $x - $barWidth,
                    $padding + $chartHeight - $height,
                    $x,
                    $padding + $chartHeight,
                    $green
                );
            }
        }

        foreach ($expenseData as $index => $value) {
            $x = $padding + ($index / max(1, $count - 1)) * $chartWidth + $gap;

            // Egreso
            $height = ($value / $maxValue) * $chartHeight;
            if ($height > 0) {
                imagefilledrectangle(
                    $image,
                    $x,
                    $padding + $chartHeight - $height,
                    $x + $barWidth,
                    $padding + $chartHeight,
                    $red
                );
            }
        }
    }

    /**
     * Dibujar leyenda horizontal debajo del gráfico
     */
    private function drawLegendHorizontal($image, int $width, int $chartHeight, $green, $red, $blue, $darkGray): void
    {
        $legendY = $chartHeight + 20;
        $itemWidth = 100;
        $spacing = 30;
        $totalWidth = 3 * $itemWidth + 2 * $spacing;
        $startX = ($width - $totalWidth) / 2;

        // Fondo de la leyenda (opcional)
        $legendBg = imagecolorallocate($image, 248, 250, 252);
        imagefilledrectangle($image, $startX - 10, $legendY - 8, $startX + $totalWidth + 10, $legendY + 22, $legendBg);
        imagerectangle($image, $startX - 10, $legendY - 8, $startX + $totalWidth + 10, $legendY + 22, $darkGray);

        // Ingresos
        $x1 = $startX;
        imagefilledrectangle($image, $x1, $legendY, $x1 + 15, $legendY + 10, $green);
        imagestring($image, 2, $x1 + 20, $legendY - 2, 'Ingresos', $darkGray);

        // Egresos
        $x2 = $x1 + $itemWidth + $spacing;
        imagefilledrectangle($image, $x2, $legendY, $x2 + 15, $legendY + 10, $red);
        imagestring($image, 2, $x2 + 20, $legendY - 2, 'Egresos', $darkGray);

        // Flujo Neto
        $x3 = $x2 + $itemWidth + $spacing;
        imagefilledrectangle($image, $x3, $legendY, $x3 + 15, $legendY + 10, $blue);
        imagestring($image, 2, $x3 + 20, $legendY - 2, 'Flujo Neto', $darkGray);
    }
}
