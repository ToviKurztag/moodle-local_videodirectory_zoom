jQuery(function ($) {
    var zoom_browser_integration = {

        init: function () {
            console.log('checkSystemRequirements');

            var browseinfo = ZoomMtg.checkSystemRequirements();
            var page_html = '<ul><li><strong>Browser Info:</strong> ' + browseinfo.browserInfo + '</li>';
            page_html += '<li><strong>Browser Name:</strong> ' + browseinfo.browserName + '</li>';
            page_html += '<li><strong>Browser Version:</strong> ' + browseinfo.browserVersion + '</li></ul>';
            // page_html += '<li><strong>Available:</strong> ' + browseinfo.features + '</li></ul>';
            $('.dpen-zoom-browser-meeting--info').html(page_html);

            ZoomMtg.preLoadWasm();
            ZoomMtg.prepareJssdk();

            this.eventHandlers();
        },

        eventHandlers: function () {
            $('#dpen-zoom-browser-meeting-join-mtg').on('click', this.loadMeeting.bind(this));
        },

        loadMeeting: function (e) {
            e.preventDefault();

            var meeting_id = $(e.currentTarget).data('meeting');
            var API_KEY = false;
            var SIGNATURE = false;
            var REDIRECTION = zvc_ajx.redirect_page;
            $('body').append('<span id="zvc-cover"></span>');
            if (meeting_id) {
                $.post(zvc_ajx.ajaxurl, {
                    action: 'get_auth',
                    noncce: zvc_ajx.zvc_security,
                    meeting_id: meeting_id,
                }).done(function (response) {
                    if (response.success) {
                        $("#zvc-cover").remove();
                        API_KEY = response.data.key;
                        SIGNATURE = response.data.sig;

                        if (API_KEY && SIGNATURE) {
                            var display_name = $('#display_name');
                            if (!display_name.val()) {
                                alert("Name is required to enter the meeting !");
                                return false;
                            }

                            var meetConfig = {
                                apiKey: API_KEY,
                                meetingNumber: parseInt(meeting_id),
                                userName: document.getElementById('display_name').value,
                                // passWord: "",
                                leaveUrl: REDIRECTION,
                                signaure: SIGNATURE,
                            };

                            ZoomMtg.init({
                                leaveUrl: REDIRECTION,
                                isSupportAV: true,
                                success: function () {
                                    ZoomMtg.join(
                                        {
                                            meetingNumber: meetConfig.meetingNumber,
                                            userName: meetConfig.userName,
                                            signature: meetConfig.signaure,
                                            apiKey: meetConfig.apiKey,
                                            // userEmail: 'email@gmail.com',
                                            // passWord: meetConfig.passWord,
                                            success: function (res) {
                                                $('#dpen-zoom-browser-meeting').hide();
                                                console.log('join meeting success');
                                            },
                                            error: function (res) {
                                                console.log(res);
                                            }
                                        }
                                    );
                                },
                                error: function (res) {
                                    console.log(res);
                                }
                            });
                        } else {
                            $("#zvc-cover").remove();
                            alert("NOT AUTHORIZED");
                        }
                    }
                });
            } else {
                $("#zvc-cover").remove();
                alert("NOT AUTHORIZED");
            }
        }
    };

    zoom_browser_integration.init();
});