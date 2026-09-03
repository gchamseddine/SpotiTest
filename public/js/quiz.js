const quizConfig = window.spotiTestQuiz;

let spotifyPlayer;
let spotifyDeviceId;
let countdownInterval = null;

let titleSolved = false;
let artistSolved = false;
let roundEnded = false;

let checkTimeout;

const clipLength = quizConfig.clipLength;

let currentTrackUri = quizConfig.trackUri;
let currentRound = quizConfig.round;

let roundVersion = 0;

const timer = document.getElementById('timer');
const answers = document.getElementById('answers');
const status = document.getElementById('status');
const skipButton = document.getElementById('skip-button');
const quitButton = document.getElementById('quit-button');
const guessInput = document.getElementById('guess-answer');
const titleStatus = document.getElementById('title-status');
const artistStatus = document.getElementById('artist-status');
const roundAnswer = document.getElementById('round-answer');
const roundHeading = document.getElementById('round-heading');


async function getSpotifyToken() {

    const response = await fetch(
        quizConfig.urls.spotifyToken
    );

    if (!response.ok) {
        throw new Error(
            'Could not retrieve Spotify token'
        );
    }

    const data = await response.json();

    return data.access_token;
}


window.onSpotifyWebPlaybackSDKReady = () => {

    spotifyPlayer = new Spotify.Player({
        name: 'SpotiTest',

        getOAuthToken: async callback => {
            try {
                const token =
                    await getSpotifyToken();

                callback(token);

            } catch (error) {
                console.error(error);
            }
        },

        volume: 0.5
    });


    spotifyPlayer.addListener(
        'ready',
        async ({ device_id }) => {

            spotifyDeviceId = device_id;

            await startRound();
        }
    );


    spotifyPlayer.addListener(
        'authentication_error',
        ({ message }) => {

            console.error(message);

            status.textContent =
                'Your Spotify session expired. Please reconnect Spotify.';
        }
    );


    spotifyPlayer.addListener(
        'account_error',
        ({ message }) => {

            console.error(message);

            status.textContent =
                'Spotify Premium is required for playback.';
        }
    );


    spotifyPlayer.addListener(
        'playback_error',
        ({ message }) => {

            console.error(message);

            if (countdownInterval !== null) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }

            status.textContent =
                'Spotify could not play this song. You can skip it.';

            skipButton.disabled = false;
            skipButton.style.display = 'block';

            disableAnswers();
        }
    );


    spotifyPlayer.connect();
};


async function startRound() {

    const thisRound = ++roundVersion;

    roundEnded = false;
    titleSolved = false;
    artistSolved = false;

    if (countdownInterval !== null) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }

    timer.textContent =
        clipLength + ' seconds';

    status.textContent = '';

    guessInput.value = '';
    guessInput.disabled = false;

    roundAnswer.textContent = '';
    roundAnswer.style.display = 'none';

    if (titleStatus) {
        titleStatus.textContent = 'Song: ?';
    }

    if (artistStatus) {
        artistStatus.textContent = 'Artist: ?';
    }

    skipButton.disabled = true;
    skipButton.style.display = 'block';

    answers.style.display = 'block';

    guessInput.focus();

    const token =
        await getSpotifyToken();

    if (thisRound !== roundVersion) {
        return;
    }

    const response = await fetch(
        'https://api.spotify.com/v1/me/player/play'
        + '?device_id='
        + encodeURIComponent(spotifyDeviceId),
        {
            method: 'PUT',

            headers: {
                'Authorization':
                    'Bearer ' + token,

                'Content-Type':
                    'application/json'
            },

            body: JSON.stringify({
                uris: [
                    currentTrackUri
                ]
            })
        }
    );

    if (thisRound !== roundVersion) {
        return;
    }

    if (!response.ok) {

        status.textContent =
            'Spotify could not play this song. You can skip it.';

        skipButton.disabled = false;

        disableAnswers();

        return;
    }

    const startResponse = await fetch(
        quizConfig.urls.roundStart,
        {
            method: 'POST'
        }
    );

    if (!startResponse.ok) {

        status.textContent =
            'Could not start the quiz timer.';

        skipButton.disabled = false;

        return;
    }

    skipButton.disabled = false;

    startCountdown(thisRound);
}


function startCountdown(thisRound) {

    if (countdownInterval !== null) {
        clearInterval(countdownInterval);
    }

    let secondsLeft = clipLength;

    timer.textContent =
        secondsLeft + ' seconds';

    countdownInterval = setInterval(
        async () => {

            if (thisRound !== roundVersion) {
                clearInterval(countdownInterval);
                return;
            }

            secondsLeft--;

            timer.textContent =
                secondsLeft + ' seconds';

            if (secondsLeft <= 0) {

                clearInterval(countdownInterval);
                countdownInterval = null;

                timer.textContent = 'Time up!';

                await finishRound();
            }

        },
        1000
    );
}


guessInput.addEventListener(
    'input',
    () => {

        clearTimeout(checkTimeout);

        checkTimeout = setTimeout(
            checkGuess,
            250
        );
    }
);


async function checkGuess() {

    const guess =
        guessInput.value.trim();

    if (guess === '') {
        return;
    }

    const response = await fetch(
        quizConfig.urls.answer,
        {
            method: 'POST',

            headers: {
                'Content-Type':
                    'application/json'
            },

            body: JSON.stringify({
                guess: guess
            })
        }
    );

    if (!response.ok) {
        return;
    }

    const result =
        await response.json();

    if (
        result.titleCorrect === true &&
        !titleSolved
    ) {

        titleSolved = true;

        if (titleStatus) {
            titleStatus.textContent =
                'Song: ' +
                result.matchedTitle +
                ' ✓';
        }

        guessInput.value = '';
    }

    if (
        result.artistCorrect === true &&
        !artistSolved
    ) {

        artistSolved = true;

        if (artistStatus) {
            artistStatus.textContent =
                'Artist: ' +
                result.matchedArtist +
                ' ✓';
        }

        guessInput.value = '';
    }

    checkRoundComplete();
}


async function checkRoundComplete() {

    let complete = false;

    if (quizConfig.guessMode === 'both') {

        complete =
            titleSolved &&
            artistSolved;

    } else if (quizConfig.guessMode === 'title') {

        complete =
            titleSolved;

    } else if (quizConfig.guessMode === 'artist') {

        complete =
            artistSolved;
    }

    if (!complete) {
        return;
    }

    timer.textContent = '';
    status.textContent = 'You got it!';

    await finishRound();
}


async function finishRound() {

    if (roundEnded) {
        return;
    }

    roundEnded = true;
    roundVersion++;

    timer.textContent = '';

    if (countdownInterval !== null) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }

    await spotifyPlayer.pause();

    disableAnswers();

    skipButton.disabled = true;

    const response = await fetch(
        quizConfig.urls.next,
        {
            method: 'POST'
        }
    );

    const data = await response.json();

    roundAnswer.textContent =
        'Answer: ' +
        data.title +
        ' - ' +
        data.artists.join(', ');

    roundAnswer.style.display = 'block';

    await new Promise(resolve =>
        setTimeout(resolve, 2000)
    );

    if (data.finished) {

        window.location.href =
            data.resultsUrl;

        return;
    }

    currentRound = data.round;
    currentTrackUri = data.trackUri;

    roundHeading.textContent =
        currentRound +
        ' / ' +
        quizConfig.totalRounds;

    await startRound();
}


skipButton.addEventListener(
    'click',
    async () => {

        status.textContent = 'Skipped!';

        await finishRound();
    }
);


quitButton.addEventListener(
    'click',
    async () => {

        if (countdownInterval !== null) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }

        await spotifyPlayer.pause();

        const response = await fetch(
            quizConfig.urls.quit,
            {
                method: 'POST'
            }
        );

        const data =
            await response.json();

        window.location.href =
            data.url;
    }
);


function disableAnswers() {

    guessInput.disabled = true;
}
