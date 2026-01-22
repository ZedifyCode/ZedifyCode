jQuery(function ($) {
  $('#nb-video-test-connection').on('click', function () {
    const button = $(this);
    const nonce = button.data('nonce');
    $('#nb-video-test-result').text('');

    $.post(NBVideoAdmin.ajaxUrl, {
      action: 'nb_video_test_connection',
      nonce: nonce,
    }).done(function (response) {
      const message = response.data && response.data.message ? response.data.message : 'Success';
      $('#nb-video-test-result').text(message);
    }).fail(function () {
      $('#nb-video-test-result').text('Connection failed.');
    });
  });

  $('.nb-video-generate').on('click', function () {
    const button = $(this);
    const postId = button.data('post-id');
    const actionType = button.data('action');
    const nonce = button.data('nonce');
    const container = button.closest('.inside').find('.nb-video-result');
    container.text('');

    $.post(NBVideoAdmin.ajaxUrl, {
      action: 'nb_video_generate',
      nonce: nonce,
      post_id: postId,
      action_type: actionType,
    }).done(function (response) {
      const message = response.data && response.data.message ? response.data.message : 'Queued';
      container.text(message);
    }).fail(function () {
      container.text('Failed to start job.');
    });
  });

  $('.nb-video-cancel').on('click', function () {
    const button = $(this);
    const postId = button.data('post-id');
    const nonce = button.data('nonce');
    const container = button.closest('.inside').find('.nb-video-result');
    container.text('');

    $.post(NBVideoAdmin.ajaxUrl, {
      action: 'nb_video_cancel',
      nonce: nonce,
      post_id: postId,
    }).done(function (response) {
      const message = response.data && response.data.message ? response.data.message : 'Cancelled';
      container.text(message);
    }).fail(function () {
      container.text('Failed to cancel job.');
    });
  });
});
