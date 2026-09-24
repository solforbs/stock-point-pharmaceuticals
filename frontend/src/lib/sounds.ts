/**
 * Part 17 — a message you cannot see should still be heard. The popup can be
 * behind another window or the user away from the till, so arrival and
 * dispatch each get a distinct chime at full volume.
 *
 * Browsers refuse audio before the first user gesture; that rejection is
 * swallowed because a silent first message is better than a console error.
 */
const receivedSound = new Audio('/sounds/message-received.mp3')
const sentSound = new Audio('/sounds/message-sent.mp3')
receivedSound.volume = 1.0
sentSound.volume = 1.0

function play(sound: HTMLAudioElement) {
  sound.currentTime = 0
  void sound.play().catch(() => {
    // Autoplay blocked until the user interacts with the page.
  })
}

export function playMessageReceived() {
  play(receivedSound)
}

export function playMessageSent() {
  play(sentSound)
}
