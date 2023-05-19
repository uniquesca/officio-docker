$(function () {
    const company_name_h = document.getElementById("company_name_h").value;
    const company_phone_h = document.getElementById("company_phone_h").value;
    const company_address_h = document.getElementById("company_address_h").value;
    const title_h = document.getElementById("title_h").value;
    const assessment_url_h = document.getElementById("assessment_url_h").value;

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
        assessment_urls[i].src = assessment_url_h;
    }

    var navItems = document.querySelectorAll(".navbar-nav .nav-item");
    const BreakException = {};
    try {
        navItems.forEach(el => {
            let loc_array = document.location.href.split("/");

            let pageName = loc_array[loc_array.length - 1],
                repPageName = pageName.replace(/[^a-zA-Z]/g, ""),
                elName = el.getAttribute("name");

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
});

function sendMessage(comp_name) {
    const messageAction = `/webs/${comp_name}/homepage/send-message`;
    const form = $("#message-form").serialize();

    $.ajax({
        url: messageAction,
        data: form,
        dataType: "json",
        async: true,
        type: "POST",
        beforeSend: function () {
        },
        complete: function (data) {
        },
        success: function (result) {
            alert("Message sent successfully!");
        },
        error: function (data) {
        }
    });
}
