/**
 * Sign Up tab headline typewriter: Hello! <-> localized greeting.
 * See docs/ui-reduce-motion.md.
 */
document.addEventListener("alpine:init", () => {
    Alpine.data(
        "tidoSignupGreeting",
        ({
            phrases = ["Hello!"],
            holdMs = 4500,
            typeMs = 80,
            deleteMs = 50,
        }) => ({
            phrases,
            holdMs,
            typeMs,
            deleteMs,
            phraseIndex: 0,
            displayText: phrases[0] ?? "Hello!",
            phase: "hold",
            timer: null,
            reducedMotion: false,

            get showCaret() {
                return !this.reducedMotion && this.phrases.length > 1;
            },

            prefersReducedMotion() {
                if (typeof window.tidoPrefersReducedMotion === "function") {
                    return window.tidoPrefersReducedMotion();
                }

                return window.matchMedia("(prefers-reduced-motion: reduce)")
                    .matches;
            },

            init() {
                this.reducedMotion = this.prefersReducedMotion();
                this.displayText = this.phrases[0] ?? "Hello!";

                if (this.phrases.length <= 1) {
                    return;
                }

                this.scheduleNext(this.holdMs);

                if (typeof this.$cleanup === "function") {
                    this.$cleanup(() => this.clearTimer());
                }
            },

            clearTimer() {
                if (this.timer !== null) {
                    clearTimeout(this.timer);
                    this.timer = null;
                }
            },

            scheduleNext(delay) {
                this.clearTimer();
                this.timer = setTimeout(() => this.tick(), delay);
            },

            tick() {
                if (!this.$el?.isConnected) {
                    this.clearTimer();
                    return;
                }

                if (this.phrases.length <= 1) {
                    return;
                }

                if (this.reducedMotion) {
                    if (this.phase === "hold") {
                        this.phraseIndex =
                            (this.phraseIndex + 1) % this.phrases.length;
                        this.displayText =
                            this.phrases[this.phraseIndex] ?? "Hello!";
                        this.scheduleNext(this.holdMs);
                    }

                    return;
                }

                switch (this.phase) {
                    case "hold":
                        this.phase = "deleting";
                        this.scheduleNext(0);
                        break;
                    case "deleting":
                        if (this.displayText.length > 0) {
                            this.displayText = this.displayText.slice(0, -1);
                            this.scheduleNext(this.deleteMs);
                        } else {
                            this.phraseIndex =
                                (this.phraseIndex + 1) % this.phrases.length;
                            this.phase = "typing";
                            this.scheduleNext(this.typeMs);
                        }
                        break;
                    case "typing": {
                        const nextTarget = this.phrases[this.phraseIndex] ?? "";

                        if (this.displayText.length < nextTarget.length) {
                            this.displayText = nextTarget.slice(
                                0,
                                this.displayText.length + 1,
                            );
                            this.scheduleNext(this.typeMs);
                        } else {
                            this.phase = "hold";
                            this.scheduleNext(this.holdMs);
                        }
                        break;
                    }
                    default:
                        this.phase = "hold";
                        this.scheduleNext(this.holdMs);
                        break;
                }
            },
        }),
    );
});
