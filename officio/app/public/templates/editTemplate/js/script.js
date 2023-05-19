$(function () {
    const comp_name = document.getElementById("entr_name").value;
    const company_name_h = document.getElementById("company_name_h").value;
    const company_phone_h = document.getElementById("company_phone_h").value;
    const company_address_h = document.getElementById("company_address_h").value;
    const title_h = document.getElementById("title_h").value;
    const assessment_url_h = document.getElementById("assessment_url_h").value;
    const json_pages = JSON.parse(document.getElementById("json_pages").value);

    const comp_names = document.getElementsByClassName("company_name");
    for (let i = 0; i < comp_names.length; i++) {
        comp_names[i].innerHTML = company_name_h;
    }

    const company_phones = document.getElementsByClassName("company_phone");
    for (let i = 0; i < company_phones.length; i++) {
        company_phones[i].innerHTML = company_phone_h;
    }

    const addresses = document.getElementsByClassName("company_address");
    for (let i = 0; i < addresses.length; i++) {
        addresses[i].innerHTML = company_address_h;
    }

    const titles = document.getElementsByClassName("nav_title");
    for (let i = 0; i < titles.length; i++) {
        titles[i].innerHTML = title_h;
    }

    const assessment_urls = document.getElementsByClassName("assessment_url");
    for (let i = 0; i < assessment_urls.length; i++) {
        console.log(assessment_url_h);
        assessment_urls[i].src = assessment_url_h;
    }

    var navItems = document.querySelectorAll(".navbar-nav .nav-item");
    const BreakException = {};
    try {
        let loc_array = document.location.href.split("/"),
            pageName = loc_array[loc_array.length - 1],
            repPageName = pageName.replace(/[^a-zA-Z]/g, "");
        navItems.forEach(el => {
            let elName = el.getAttribute("name");

            if (elName === repPageName) {
                el.classList.add("active");
                throw BreakException;
            }
        });
        document
            .querySelector('.navbar-nav .nav-item[name="homepage"]')
            .classList.add("active");
    } catch (e) {
    }

    const editor = grapesjs.init({
        // Indicate where to init the editor. You can also pass an HTMLElement
        container: "#gjs",
        // Get the content for the canvas directly from the element
        // As an alternative we could use: `components: '<h1>Hello World Component!</h1>'`,
        fromElement: true,
        autorender: true,
        // Size of the editor
        width: "auto",
        height: "100%",
        // Disable the storage manager for the moment
        storageManager: false,
        styleManager: {},
        plugins: ["gjs-preset-webpage", "gjs-iframe"],
        pluginsOpts: {
            "gjs-preset-webpage": {},
            "gjs-iframe": {}
        },
        canvas: {
            styles: [
                topBaseUrl + "/assets/plugins/bootstrap/dist/css/bootstrap.min.css"
            ],
            scripts: [
                topBaseUrl + "/assets/plugins/jquery/dist/jquery.min.js",
                topBaseUrl + "/assets/plugins/bootstrap/dist/js/bootstrap.min.js"
            ]
        }
    });

    const available_pages = Object.keys(json_pages).map(page_name => {
        if (
            json_pages[page_name]["available"] &&
            json_pages[page_name]["available"] != 0
        ) {
            return (
                '<li name="' +
                page_name +
                '" class="nav-item"> <a class="nav-link" href="/webs/' +
                comp_name +
                "/" +
                page_name +
                '">' +
                json_pages[page_name]["name"] +
                "</a> </li>"
            );
        } else {
            return "";
        }
    });
    const nav_li = available_pages.join("");

    editor.on("component:add", model => {
        let mClassName = null;
        if (model.attributes.classes.models.length > 0) {
            mClassName = model.attributes.classes.models[0].attributes.name
                ? model.attributes.classes.models[0].attributes.name
                : null;
        }

        if (
            !model.attributes.type ||
            (model.attributes.type == "text" &&
                (model.attributes.tagName == "h1" ||
                    model.attributes.tagName == "p")) ||
            mClassName === "company_address" ||
            mClassName === "company_phone" ||
            mClassName === "company_name"
        ) {
            model.addClass(model.cid);
        }
    });

    const blockManager = editor.BlockManager;
    blockManager.add("contact-form", {
        label: "Contact form",
        content:
            '<form id="message-form" class="p-3">\
              <div class="form-group">\
              <label for="name-form">Your name</label>\
              <input name="name" type="text" class="form-control"\
               id="name-form" aria-describedby="nameHelp" placeholder="Enter name">\
               </div>\
               <div class="form-group">\
              <div class="form-group">\
              <label for="email-form">Email address</label>\
              <input name="email" type="email" class="form-control"\
               id="email-form" aria-describedby="emailHelp" placeholder="Enter email" required>\
               </div>\
               <div class="form-group">\
              <label for="phone-form">Phone</label>\
              <input name="phone" type="phone" class="form-control"\
               id="phone-form" aria-describedby="phoneHelp" placeholder="Phone">\
               </div>\
               <div class="form-group">\
              <label for="textarea-form">Message</label>\
              <textarea name="message" type="text" class="form-control" required rows="5" style="resize: none;"\
               id="textarea-form" placeholder="Enter your message"></textarea>\
               </div>\
               <button onclick="javascript:sendMessage(\'' +
            comp_name +
            '\')" class="btn btn-primary" type="button">Send</button>\
               </form>',
        category: "Main",
        attributes: {
            title: "Insert h1 block",
            class: "fa fa-wpforms"
        }
    });

    blockManager.add("main-navbar", {
        label: "Navbar",
        content:
            '\
              <nav class="navbar navbar-expand-lg">\
              <a class="navbar-brand nav_title" href="#">' +
            title_h +
            '</a>\
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" \
         aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">\
          <span class="navbar-toggler-icon"></span>\
        </button>\
        <div class="collapse navbar-collapse" id="navbarSupportedContent">\
              <ul class="navbar-nav ml-auto">' +
            nav_li +
            "</ul>\
              </div>\
              </nav>",
        category: "Main",
        attributes: {
            title: "Insert h1 block",
            class: "fa fa-bars"
        }
    });

    blockManager.add("container", {
        label: "Container",
        content: '<div class="container"></div>',
        category: "Main",
        attributes: {
            title: "Insert h1 block",
            class: "fa fa-square-o"
        }
    });

    blockManager.add("container-centered", {
        label: "Container-centered",
        content:
            '<div class="container d-flex flex-wrap justify-content-around align-items-center"></div>',
        category: "Main",
        attributes: {
            title: "Container-centered",
            class: "fa fa-dot-circle-o"
        }
    });

    blockManager.add("container-vertical-centered", {
        label: "Container-vertical-centered",
        content:
            '<div class="container d-flex flex-wrap justify-content-around align-items-center h-100"></div>',
        category: "Main",
        attributes: {
            title: "Container-vertical-centered",
            class: "fa fa-dot-circle-o"
        }
    });

    blockManager.add("col-2", {
        label: "Col-2",
        content:
            '<div class="row"><div class="col-sm"></div><div class="col-sm"></div></div>',
        category: "Main",
        attributes: {
            title: "Col-2",
            class: "fa fa-columns"
        }
    });

    blockManager.add("col-3", {
        label: "Col-3",
        content:
            '<div class="row"><div class="col-sm"></div><div class="col-sm"></div><div class="col-sm"></div></div>',
        category: "Main",
        attributes: {
            title: "Col-3",
            class: "fa fa-columns"
        }
    });

    blockManager.add("company_address", {
        label: "Company address",
        content:
            ' <div class="company_address" data-gjs-editable="false">' +
            company_address_h +
            "</div>",
        category: "Main",
        attributes: {
            title: "Company_address",
            class: "fa fa-address-card"
        }
    });

    blockManager.add("company_phone", {
        label: "Company phone",
        content:
            ' <div  class="company_phone" data-gjs-editable="false">' +
            company_phone_h +
            "</div>",
        category: "Main",
        attributes: {
            title: "Company_phone",
            class: "fa fa-phone"
        }
    });

    blockManager.add("company_name", {
        label: "Company name",
        content:
            ' <div  class="company_name" data-gjs-editable="false">' +
            company_name_h +
            "</div>",
        category: "Main",
        attributes: {
            title: "Company_name",
            class: "fa fa-building-o"
        }
    });

    var blocks = blockManager.getAll();

    var filtered = blocks.filter(block => {
        if (block.get("category").attributes) {
            return (
                block.get("category").attributes.id == "Forms" ||
                block.get("category").attributes.id == "Extra" ||
                block.attributes.id == "column1" ||
                block.attributes.id == "column2" ||
                block.attributes.id == "column3" ||
                block.attributes.id == "column3-7"
            );
        }
    });
    filtered.forEach(function (block) {
        blockManager.remove(block.get("id"));
    });

    editor.Panels.addButton("options", [
        {
            id: "save-db",
            className: "fa fa-floppy-o",
            command: "save-db",
            attributes: {
                title: "Save DB"
            }
        }
    ]);
    const commands = editor.Commands;

    editor.Panels.addButton("options", [
        {
            id: "save-db",
            className: "fa fa-floppy-o",
            command: "save-db",
            attributes: {
                title: "Save DB"
            }
        }
    ]);

    editor.Panels.getButton("options", "sw-visibility").set("active", 1);

    const builderNav = document.getElementById("builder-nav");
    editor.on("run:preview", () => (builderNav.style.display = "none"));
    editor.on("stop:preview", () => (builderNav.style.display = "flex"));

    // Add the command
    commands.add("save-db", {
        run: function (editor, sender) {
            sender && sender.set("active", 0); // turn off the button
            editor.store();
            const header = editor.DomComponents.getWrapper().find("#header")[0];
            const footer = editor.DomComponents.getWrapper().find("#footer")[0];
            const content = editor.DomComponents.getWrapper().find("#content")[0];
            const headerCss = editor.CodeManager.getCode(header, "css", {
                cssc: editor.CssComposer
            });
            const footerCss = editor.CodeManager.getCode(footer, "css", {
                cssc: editor.CssComposer
            });
            const mainCss = `${headerCss} \n ${footerCss}`;
            const contentCss = editor.CodeManager.getCode(content, "css", {
                cssc: editor.CssComposer
            });
            const headerHtml = compToHtml(header.components().models);
            const footerHtml = compToHtml(footer.components().models);
            const contentHtml = compToHtml(content.components().models);

            const comp_name = document.getElementById("entr_name").value;
            const page_name = document.getElementById("page_name").value;

            $.ajax({
                url: `/builder/${comp_name}/save-data`,
                data: {
                    headerHtml: headerHtml,
                    footerHtml: footerHtml,
                    contentHtml: contentHtml,
                    mainCss: mainCss,
                    contentCss: contentCss,
                    page_name: page_name && page_name != "/" ? page_name : "homepage"
                },
                dataType: "json",
                async: true,
                type: "POST",
                beforeSend: function () {
                },
                complete: function (data) {
                },
                success: function (result) {
                    alert("Website is saved!");
                },
                error: function (data) {
                }
            });
        }
    });

    function compToHtml(comp) {
        const compHtml = comp.map(function (item) {
            return item.toHTML();
        });
        const html = compHtml.join("\n");
        return html;
    }
});
