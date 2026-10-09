async function retakeCurrentGame(button) {
  if (button?.disabled) return;

  const originalLabel = button?.innerHTML;
  if (button) {
    button.disabled = true;
    button.innerHTML = "Preparing retake…";
  }

  try {
    if (window.gameResultSavePromise) {
      await window.gameResultSavePromise;
    }

    const response = await fetch("prepare_game_retake.php", {
      method: "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to prepare the retake.");
    }

    window.location.reload();
  } catch (error) {
    if (button) {
      button.disabled = false;
      button.innerHTML = originalLabel;
    }
    window.alert(
      error.message || "Unable to prepare the retake. Please try again.",
    );
  }
}
