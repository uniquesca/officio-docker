function getChartDefinedRandomColor(i) {
    var chartColors = {
        red:      'rgb(255, 99, 132)',
        green:    'rgb(75, 192, 192)',
        blue:     'rgb(54, 162, 235)',
        orange:   'rgb(255, 159, 64)',
        yellow:   'rgb(255, 205, 86)',
        purple:   'rgb(153, 102, 255)',
        grey:     'rgb(201, 203, 207)',
        maroon:   'rgb(128, 0, 0)',
        brown:    'rgb(170, 110, 40)',
        olive:    'rgb(128, 128, 0)',
        teal:     'rgb(0, 128, 128)',
        navy:     'rgb(0, 0, 128)',
        black:    'rgb(0, 0, 0)',
        lime:     'rgb(210, 245, 60)',
        cyan:     'rgb(70, 240, 240)',
        magenta:  'rgb(240, 50, 230)',
        pink:     'rgb(250, 190, 190)',
        apricot:  'rgb(255, 215, 180)',
        beige:    'rgb(255, 250, 200)',
        mint:     'rgb(170, 255, 195)',
        lavender: 'rgb(230, 190, 255)'
    };

    var colorNames = Object.keys(chartColors);

    return (i >= colorNames.length) ? getRandomRGBColor() : colorNames[i];
}

function getRandomRGBColor() {
    var num = Math.round(0xffffff * Math.random());
    var r   = num >> 16;
    var g   = num >> 8 & 255;
    var b   = num & 255;
    return 'rgb(' + r + ', ' + g + ', ' + b + ')';
}