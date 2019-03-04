/**
 * @file
 * Share content using embed markup.
 */

(($, { behaviors }, { socialHub }) => {
  behaviors.socialHubShareEmbed = {
    debug: false,
    attach() {
      const { debug, instances } = socialHub;
      this.debug = debug;

      if (this.debug === true) {
        console.log(instances);
      }

      for (let i = 0; i < instances.length; i++) {
        $(instances[i])
          .once('social_hub--share__embed')
          .each((j, x) => {
            const $elem = $(x);
            const _settings = $elem.data('social-hub');

            if (this.debug === true) {
              console.log(_settings);
            }

            $elem.on('click', this.toggleEmbed);

            $(`[data-referenced-by="${$elem.attr('id')}"]`)
              .once('social_hub--clip_it')
              .each((k, y) => {
                $(y).on('click', this.copyOnClick);
              });
          });
      }
    },
    /**
     * Toggle embed element.
     *
     * @param {Event} e Event instance.
     */
    toggleEmbed(e) {
      const $this = $(e.target);
      const $embed = $(`[data-referenced-by="${$this.attr('id')}"]`);
      $embed.toggleClass('element-invisible');
      $this.toggleClass('active');

      if (this.debug === true) {
        const isActive = $this.hasClass('active');
        const isVisible = !$embed.hasClass('element-invisible');
        console.log(`Active: ${isActive}, Visible: ${isVisible}`);
      }
    },
    /**
     * Copy value on click.
     *
     * @param {Event} e Event instance.
     */
    copyOnClick(e) {
      copyTextToClipboard($(e).target.val());
    },
  };
})(jQuery, Drupal, drupalSettings);
