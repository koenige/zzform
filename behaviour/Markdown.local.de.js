// Usage:
//
// var myConverter = new Markdown.Editor(myConverter, null, { strings: Markdown.local.de });

(function () {
        Markdown.local = Markdown.local || {};
        Markdown.local.de = {
        bold: "Fett <strong> Ctrl+B",
        boldexample: "Text in fett",

        italic: "Kursiv <em> Ctrl+I",
        italicexample: "Text in kursiv",

        link: "Hyperlink <a> Ctrl+L",
        linkdescription: "Beschreibung des Links",
        linkdialog: "<p><b>Hyperlink einfügen</b></p><p>http://example.com/ \"optionaler Titel\"</p>",

        quote: "Zitat <blockquote> Ctrl+Q",
        quoteexample: "Zitat",

        code: "Codeauszug <pre><code> Ctrl+K",
        codeexample: "Auszug aus Code",

        image: "Bild <img> Ctrl+G",
        imagedescription: "Beschreibung des Bilds",
        imagedialog: "<p><b>Bild einfügen</b></p><p>http://example.com/images/diagram.jpg \"optionaler Titel\"</p>",

        olist: "Nummerierung <ol> Ctrl+O",
        ulist: "Aufzählungszeichen <ul> Ctrl+U",
        litem: "Listenelement",

        heading: "Titel <h1>/<h2> Ctrl+H",
        headingexample: "Titel",

        hr: "Trennlinie horizontal <hr> Ctrl+R",

        undo: "Rückgängig - Ctrl+Z",
        redo: "Wiederholen - Ctrl+Y",
        redomac: "Wiederholen - Ctrl+Shift+Z",

        help: "Markdown-Hilfe"
    };
})();