(function() {

    document.querySelector("button.decide.alone").addEventListener("click", function(event) {

        console.log("test");
        let data = new FormData();
        data.append("mode", "alone");

        fetch("/start", {
            method: "POST",
            headers: {
                //"Content-Type": "application/json"
            },
            body: data
        }).then(response => response.json()).then(json => {


            if(json.result == 'success') {

                document.querySelector("#questionnaire_id").value = json.data.questionnaire_id;

                if(typeof json.data.second_link != "undefined") {
                    document.querySelector(".generated-link").innerText = json.data.second_link;
                    document.querySelector(".start-alone").style.display = "block";
                }
            }
        });
    });

    document.querySelector("button.decide.together").addEventListener("click", function(event) {

        let data = new FormData();
        data.append("mode", "together");

        fetch("/start", {
            method: "POST",
            headers: {
                //"Content-Type: application/json"
            },
            body: data
        }).then(response => response.json()).then(json => {


            app.partner = true;

            if(json.result == 'success') {

                document.querySelector("#questionnaire_id").value = json.data.questionnaire_id;

                if(typeof json.data.second_link == "undefined") {
                    document.querySelector(".start-together").style.display = "block";
                }
            }
        });
    });


})();