<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="keywords" content="Photos, Viewer">
        <meta name="description" content="Photo Gallery">
        <meta name="author" content="Anon">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" href="favicon.ico">
        <title>Openchan</title>
        <script defer src="userstyles.js"></script>
        <link rel="stylesheet" href="styles/base.css">
    </head>
    <body>
        <div id="nav">&nbsp;
        <span class='left'>
            <?php include 'nav.php';
            ?>
        </span>
        <span class="right">
        <select name="cars" id="userstyleselecter" style="margin-top: -5px;" onchange="userstyle();">
            <option value="Light">Light</option>
            <option value="Dark">Dark</option>
            <option value="Yotsuba">Yotsuba</option>
            <option value="Yotsuba B">Yotsuba B</option>
        </select>
        </span>
        </div>

        <div id="head">
            <a href="index.php">
            <img src="static/openchan.png" style="width: 45%;margin: 10px;">
</a>
            <h1> </h1> <div class="rad">
                    <table>
                    <tr>
                    <td class="head">
                    <b>ABOUT OPENCHAN</b>
                    </td>
                    </tr>
                    <tr>
                    <td>
                        We talk over here IRAC for life  
                    </td>
                    </tr>
                    </table>

                    <table>
                    <tr>
                    <td class="head">
                    <b>Title</b>
                    </td>
                    <td class="head">
                    <b>Description</b>
                    </td>
                    <td class="head">
                    <b>Posts</b>
                    </td>
                    <td class="head">
                    <b>Updated</b>
                    </td>
                    </tr>


                    <tr>
                    <td>
                    <a href="b/">/b/ Random</a>
                    </td>
                    <td>
                    Off-topic disscussion.
                    </td>
                    <td>
                    <?php echo file_get_contents('b/postcount.txt') ?>
                    </td>
                    <td>
                    <?php echo file_get_contents('b/lastupdated.txt') ?>
                    </td>
                    </tr>

                    </table>
</div>
                    <table>
                    <tr>
                    <td class="head">
                    <b>Stats</b>
                    </td>
                    </tr>
                    <tr>
                    <td>
                    <b>Total Posts: </b> <?php echo file_get_contents('overchan/postcount.txt') ?>
                    </td>
                    </tr>
                    </table>




        </div>

        <!-- <div id="content">
        </div> -->
        <!-- <div id="footer"></div> -->
        <div id="pageparam"><?php
            echo $_GET['token'];
        ?></div>
        
    </body>
</html>
