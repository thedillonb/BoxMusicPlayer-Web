<?php
    // Include the Box_Rest_Client class
    include('lib/Box_Rest_Client.php');
                                      
    // Set your API Key. If you have a lot of pages reliant on the 
    // api key, then you should just set it statically in the 
    // Box_Rest_Client class.
    $api_key = '5jdn6dhdq4jz4mcafvnmjkse9pe0rgu0';
    $box_net = new Box_Rest_Client($api_key);
    $box_net->auth_token = $box_net->authenticate();
    $songs = Array();

    function recurseFolder($folder) 
    {
        global $songs;
        global $box_net;
        foreach ($folder->file as $arr)
        {
            if (strpos($arr->attr('file_name'), '.mp3') === false) continue;
            $name = str_replace('.mp3', '', $arr->attr('file_name'));
            $songs[] = array('name' => $name, 'url' => $arr->download_url($box_net));
        }
        
        foreach ($folder->folder as $arr)
            recurseFolder($arr);
    }

    $folder = $box_net->folder(0, array('params' => array('nozip', 'simple')));
    recurseFolder($folder);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Box Music</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black" />
    <link rel="stylesheet" href="http://code.jquery.com/mobile/1.1.0/jquery.mobile-1.1.0.min.css" />
    <script src="http://code.jquery.com/jquery-1.7.1.min.js"></script>
    <script src="http://code.jquery.com/mobile/1.1.0/jquery.mobile-1.1.0.min.js"></script>
    <script src="soundmanager2.js"></script>
    <style>
        .full-width-buttons .ui-btn
        {
            display: block;
        }
        div[data-role='footer']
        {
            padding-left: 0;
            padding-right: 0;
        }
        
        .ui-li .ui-btn-inner a.ui-link-inherit
        {
            padding: .1em 15px;
        }
        
        #songInfo
        {
            font-size: 11px;
            text-align: center;
        }
        
        #songCurrentpos, #songDuration
        {
            min-width: 42px;
        }
        
        .right-align
        {
            float: right;
        }
        
        #songOptionsButton .ui-icon
        {
            top: 50%;
            margin-top: -9px;
        }
        
        .containing-element .ui-slider-switch
        {
            width: 100%;
        }
        
        input.ui-slider-input {display: none;}
        
        .slider-shit .ui-btn
        {
            margin-left: -15px;
            margin-top: -15px;
        }
        
        .slider-shit .ui-slider
        {
            width: 100%;
            top: 3px;
            margin: 0;
            opacity: 1;
        }

        .slider-shit
        {
            padding: 0 20px;
        }
        
    </style>
    <script type="text/javascript">

		//A pointer to the currently playing song
        var playingSong = null;

        //The id into the song array
        var playingSongId = null;

        //The sound id for soundmanager
        var mySound = 'mySound';

        //Whether repeat is on
        var repeat = false;

        //Whether shuffle is on
        var shuffle = false;

        //Grab the songs
        var songs = [
        <?php
            foreach ($songs as $song)
                echo "{name: '" . $song["name"] . "', url: '" . $song["url"] . "'},";
        ?>
        ];

        /*
        * Document ready!
        */
        $(function () {
            
            //Prefetch all pages
            $.mobile.loadPage("#home", { showLoadmsg: false });
            $.mobile.loadPage("#player", { showLoadmsg: false });
            $.mobile.loadPage("#songOptionsPage", { showLoadmsg: false });
            
            //Apprently I have to initialize it...
            //$('#songList').listview();
            $("#posSlider").slider("disable");

            //Set the playing info to nothing
            setPlayingInfo();

            
            var songList = $("#songList");
            for (var i = 0; i < songs.length; i++) {
                var a = $("<a href='#player'><h3 class='ui-li-heading'>" + songs[i].name + "</h3></a>");
                $(a).click({ id: i }, function (event) {
                    playSong(event.data.id);
                });
                songList.append($("<li></li>").append(a));
            }
            $(songList).listview("refresh");

            $("#previousSong").click(playPreviousSong);
            $("#nextSong").click(playNextSong);

            //Play button was clicked
            $("#playSong").click(function () {

                //Nothing playing? Start from the begining
                if (playingSongId == null) {
                    playSong(0);
                    return;
                }

                //Toggle the pause
                setPauseState(soundManager.togglePause(mySound).paused);
            });

            //Toggle the shuffle button
            $("#shuffleSlider").change(function () {
                shuffle = $(this).attr('value') == 'on' ? true : false;
            });

            //Toggle the shuffle button
            $("#repeatSlider").change(function () {
                repeat = $(this).attr('value') == 'on' ? true : false;
            });
            
            $('#posSlider').change(function(){
                var slider_value = $(this).val()
                //soundManager.setPosition(mySound, slider_value);
            });
        });

        /*
        * Play a song with a specific ID
        */
        function playSong(id) {
            //Stop the sound from playing
            soundManager.destroySound(mySound);

            //If we're under, just go to the end!
            if (id < 0)
                id = songs.length - 1;

            //If we're over, choose what to do via the repeat flag
            if (id >= songs.length)
                    id = 0;

            //Save some variables
            playingSongId = id;
            playingSong = songs[playingSongId];

            //Create the sound and begin playing whenever!
            soundManager.createSound({
                id: mySound,
                url: playingSong.url,
                autoPlay: true,
                stream: true,
                onplay: function () {
                    setPlayingInfo(playingSong.name);
                    setPauseState(false);
                    setPlayTime();
                },
                onfinish: function() {
                    //We'll only continue if we're shuffling or repeating if we past the end...
                    if (shuffle == false && (playingSongId + 1 >= songs.length) && repeat == false) {
                        //No more songs...
                        setPlayingInfo();
                        setPauseState(false);
                        setPlayTime();
                        playingSong = null;
                        playingSongId = null;
                        return;
                    }
                    else {
                        playNextSong();
                    }
                },
                onload: function () {
                    //We fully loaded this song, let's update it's duration incase
                    //we don't know...
                    playingSong.duration = this.duration / 1000;
                },
                whileplaying: function () {
                    setPlayTime(this.position / 1000, playingSong.duration, this.durationEstimate / 1000);
                }
            });
        }

        /*
        * Play the next song
        */
        function playNextSong() {
            //If shuffle is on, we shall shuffle
            if (shuffle == true)
                return shuffleSong();
            playSong(playingSongId + 1);
        }

        /*
        * Play the previous song
        */
        function playPreviousSong() {
            //If shuffle is on, we shall shuffle
            if (shuffle == true)
                return shuffleSong();
            playSong(playingSongId - 1);
        }

        /*
        * Shuffle the next song
        */
        function shuffleSong() {
            var i = Math.floor((Math.random() * songs.length));
            
            //I don't want to listen to the same song...
            if (playingSongId == i && songs.length > 1) {
                if (--i >= songs.length) 
                    i = 0;
            }
            
            //Play the song!
            playSong(i);
        }

        /*
        * Set the playing information elements
        */
        function setPlayingInfo(name) {
            //Passing nothing into this function results in it's visibility being
            //changed to hide the title & artist fields
            if (name == undefined) {
                $("#nowPlayingButton").hide();
            } else {
                $("#nowPlayingButton").show();
            }

            //Set the name and artist elements
            $("#songName").text(name == undefined ? "Unknown" : name);

            //We need to refresh the list view because we modified the footer and
            //it takes up more space now!
            $("#songList").listview("refresh");
        }

        /*
        * Set the pause state of the play button
        */
        function setPauseState(paused) {
            if (paused) {
                $("#playSong .ui-btn-text").text("Play");
            } else {
                $("#playSong .ui-btn-text").text("Pause");
            }
        }

        /*
        * Set the Play clock
        */
        function setPlayTime(current, duration, estimateDuration) {
            function pad(number, length) {
                var str = '' + number;
                while (str.length < length) 
                    str = '0' + str;
                return str;
            }

            //Make sure there is a current time...
            if (current < 0 || isNaN(current)) {
                $("#songCurrentpos").html("&nbsp;");
            } else {
                //Some value will always be visible.
                $("#songCurrentpos").text(pad(Math.floor(current / 60), 2) + ":" + pad(Math.floor(current % 60), 2));
                $("#posSlider").val(Math.floor(current));
            }

            if (duration < 0 || isNaN(duration)) {
                $("#songDuration").html("&nbsp;");
                
                //We'll take the estimate...
                if (estimateDuration !== 'undefined')
                    $("#posSlider").attr('max', estimateDuration);
            } else {
                $("#songDuration").text(pad(Math.floor(duration / 60), 2) + ":" + pad(Math.floor(duration % 60), 2));
                $("#posSlider").attr('max', Math.floor(duration));
            }
                
            $("#posSlider").slider("refresh");
        }
    
    </script>
</head>
<body>
    <div data-role="page" data-url="home">
        <div data-role="header" data-id="header1" data-position="fixed">
            <h1 id="H1">Box Music</h1>
            <a href="#player" data-icon="arrow-r" class="ui-btn-right" id="nowPlayingButton" data-iconpos="notext">Playing</a>
        </div>
        <div data-role="content">
            <ul data-role="listview" data-inset="false" data-filter="true" id="songList">
            </ul>
        </div>
    </div>

    <!-- Player -->
    <div data-role="page" id="player">
        <div data-role="header" data-id="header1" data-position="fixed">
            <a href="#home" data-icon="home" data-iconpos="notext">Home</a>
            <h1 id="songName">Nothing Playing...</h1>
        </div>
        <div data-role="content" style="height: 100%;">
            <img src="note.png" style="position: absolute; left: 50%; margin-left: -128px; top: 50%; margin-top: -171px;" />
        </div>
        <div data-role="footer" data-position="fixed">
            <table style="margin-left: 5px; margin-right: 5px;">
                <tr>
                    <td><p id="songCurrentpos">0:00</p></td>
                    <td width="100%" class="slider-shit"><input type="range" name="slider" id="posSlider" value="0" min="0" max="100" width="100%" data-theme="d" data-highlight="true"/></td>
                    <td><p id="songDuration">0:00</p></td>
                </tr>
            </table>
            <table>
                <tr>
                    <td width="100%">
                        <div data-role="controlgroup" data-type="horizontal">
                            <fieldset class="ui-grid-b full-width-buttons">
                                <div class="ui-block-a">
                                    <a data-role="button" data-iconpos="top" data-icon="back" id="previousSong">Previous</a>
                                </div>
                                <div class="ui-block-b">
                                    <a data-role="button" data-iconpos="top" data-icon="arrow-r" id="playSong">Play</a>
                                </div>
                                <div class="ui-block-c">
                                    <a data-role="button" data-iconpos="top" data-icon="forward" id="nextSong">Next</a>
                                </div>
                            </fieldset>
                        </div>
                    </td>
                    <td>
                        <div data-role="controlgroup" data-type="horizontal">
                            <a href="#songOptionsPage" id="songOptionsButton" data-role="button" data-iconpos="top" data-icon="gear" data-iconpos="notext" data-rel="dialog">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</a>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Dialog -->
    <div data-role="dialog" data-url="songOptionsPage" id="songOptionsPage">
        <div data-role="content">
            <h3>Music Options</h3>
            <div class="containing-element">
                <select name="slider" id="shuffleSlider" data-role="slider" data-track-theme="a">
                    <option value="off">Shuffle Off</option>
                    <option value="on">Shuffle On</option>
                </select>
            </div>
            <div class="containing-element">
                <select name="slider" id="repeatSlider" data-role="slider" data-track-theme="a">
                    <option value="off">Repeat Off</option>
                    <option value="on">Repeat On</option>
                </select>
            </div>
            <a data-role="button" data-iconpos="left" data-icon="back" data-rel="back">Back</a>
        </div>
    </div>
</body>
</html>
