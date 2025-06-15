<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $teacher_role = $_POST['teacher_role'] ?? '';
    $subjects = $_POST['subjects'] ?? '';
    $specialization = $_POST['specialization'] ?? ($user['specialization'] ?? '');
    $year_of_study = $_POST['year_of_study'] ?? ($user['year_of_study'] ?? '');
    $profile_photo = $user['profile_photo'] ?? null;

    // Handle cropped image
    if (isset($_POST['cropped_image']) && !empty($_POST['cropped_image'])) {
        $cropped_image_data = $_POST['cropped_image'];
        
        // Remove the data URL prefix to get the base64 encoded image data
        if (preg_match('/^data:image\/(\w+);base64,/', $cropped_image_data, $type)) {
            $cropped_image_data = substr($cropped_image_data, strpos($cropped_image_data, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif

            if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
                $error = 'Invalid image type.';
            } else {
                $cropped_image_data = base64_decode($cropped_image_data);

                if ($cropped_image_data === false) {
                    $error = 'Failed to decode image data.';
                } else {
                    $new_filename = uniqid('profile_') . '.' . $type;
                    $upload_path = 'uploads/profile_photos/' . $new_filename;

                    // Delete old profile photo if exists
                    if ($profile_photo && file_exists($profile_photo)) {
                        unlink($profile_photo);
                    }

                    if (file_put_contents($upload_path, $cropped_image_data)) {
                        $profile_photo = $upload_path;
                    } else {
                        $error = 'Failed to save profile photo.';
                    }
                }
            }
        } else {
            $error = 'Invalid image data.';
        }
    }
    // Handle regular file upload as fallback
    else if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['profile_photo']['type'], $allowed_types)) {
            $error = 'Invalid file type. Please upload a JPEG, PNG, or GIF image.';
        } elseif ($_FILES['profile_photo']['size'] > $max_size) {
            $error = 'File size too large. Maximum size is 5MB.';
        } else {
            $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('profile_') . '.' . $file_extension;
            $upload_path = 'uploads/profile_photos/' . $new_filename;

            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                // Delete old profile photo if exists
                if ($profile_photo && file_exists($profile_photo)) {
                    unlink($profile_photo);
                }
                $profile_photo = $upload_path;
            } else {
                $error = 'Failed to upload profile photo.';
            }
        }
    }

    if (empty($name)) {
        $error = 'Name is required';
    } else {
        try {
            $sql = 'UPDATE users SET name = ?, email = ?';
            $params = [$name, $email];

            if ($profile_photo) {
                $sql .= ', profile_photo = ?';
                $params[] = $profile_photo;
            }

            if ($user_role === 'student') {
                $sql .= ', specialization = ?, year_of_study = ?';
                $params[] = $specialization;
                $params[] = $year_of_study;
            } else if ($user_role === 'teacher') {
                $sql .= ', teacher_role = ?, subjects = ?';
                $params[] = $teacher_role;
                $params[] = $subjects;
            }

            $sql .= ' WHERE id = ?';
            $params[] = $user_id;

            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $success = 'Profile updated successfully';
                // Refresh user data
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $error = 'Failed to update profile';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred while updating profile: ' . $e->getMessage();
        }
    }
}

ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="css/profile.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<div class="profile-outer">
  <div class="profile-card">
    <div class="profile-header">
      <div class="profile-title">Edit Profile</div>
      <div style="height: 18px;"></div>
      <div class="profile-photo-container">
        <?php if (!empty($user['profile_photo'])): ?>
          <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Photo" class="profile-photo">
        <?php else: ?>
          <div class="profile-photo-placeholder">
            <i class="fa fa-user-cog"></i>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($error): ?>
      <div class="profile-alert profile-alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="profile-alert profile-alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <form method="POST" action="" enctype="multipart/form-data" style="width:100%;">
      <div class="form-group">
        <label for="profile_photo" class="photo-upload-label">
          <span class="upload-text">Upload Profile Photo</span>
        </label>
        <input type="file" name="profile_photo" id="profile_photo" accept="image/*" class="photo-upload-input">
        <input type="hidden" name="cropped_image" id="cropped_image">
      </div>
      <!-- Cropper Modal -->
      <div id="cropperModal" class="modal">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Crop Profile Photo</h3>
            <span class="close">&times;</span>
          </div>
          <div class="modal-body">
            <div class="img-container">
              <img id="cropperImage" src="" alt="Image to crop">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" id="cancelCrop">Cancel</button>
            <button type="button" class="btn-primary" id="cropImage">Crop & Save</button>
          </div>
        </div>
      </div>
      <div class="form-group">
        <span class="input-icon"><i class="fa fa-user"></i></span>
        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required placeholder="Full Name">
      </div>
      <div class="form-group">
        <span class="input-icon"><i class="fa fa-envelope"></i></span>
        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required placeholder="Email">
      </div>
      <?php if ($user_role === 'student'): ?>
        <div class="form-group">
          <span class="input-icon"><i class="fa fa-id-card"></i></span>
          <input type="text" name="faculty_number" class="form-control" value="<?php echo htmlspecialchars($user['faculty_number'] ?? ''); ?>" required placeholder="Faculty Number" readonly>
        </div>
        <div class="form-group">
          <span class="input-icon"><i class="fa fa-graduation-cap"></i></span>
          <select name="specialization" class="form-control" required>
            <option value="">Select Specialization</option>
            <option value="Software Engineering" <?php if(($user['specialization'] ?? '')=='Software Engineering') echo 'selected'; ?>>Software Engineering</option>
            <option value="Information Systems" <?php if(($user['specialization'] ?? '')=='Information Systems') echo 'selected'; ?>>Information Systems</option>
            <option value="Computer Science" <?php if(($user['specialization'] ?? '')=='Computer Science') echo 'selected'; ?>>Computer Science</option>
            <option value="Applied Mathematics" <?php if(($user['specialization'] ?? '')=='Applied Mathematics') echo 'selected'; ?>>Applied Mathematics</option>
            <option value="Informatics" <?php if(($user['specialization'] ?? '')=='Informatics') echo 'selected'; ?>>Informatics</option>
          </select>
        </div>
        <div class="form-group">
          <span class="input-icon"><i class="fa fa-calendar-alt"></i></span>
          <select name="year_of_study" class="form-control" required>
            <option value="">Select Year</option>
            <option value="1st year" <?php if(($user['year_of_study'] ?? '')=='1st year') echo 'selected'; ?>>1st year</option>
            <option value="2nd year" <?php if(($user['year_of_study'] ?? '')=='2nd year') echo 'selected'; ?>>2nd year</option>
            <option value="3rd year" <?php if(($user['year_of_study'] ?? '')=='3rd year') echo 'selected'; ?>>3rd year</option>
            <option value="4th year" <?php if(($user['year_of_study'] ?? '')=='4th year') echo 'selected'; ?>>4th year</option>
          </select>
        </div>
      <?php elseif ($user_role === 'teacher'): ?>
        <div class="form-group">
          <span class="input-icon"><i class="fa fa-briefcase"></i></span>
          <input type="text" name="teacher_role" class="form-control" value="<?php echo htmlspecialchars($user['teacher_role'] ?? ''); ?>" placeholder="e.g., Professor, Assistant Professor" required>
        </div>
        <div class="form-group">
          <span class="input-icon"><i class="fa fa-book"></i></span>
          <textarea name="subjects" class="form-control" rows="3" placeholder="Enter subjects you teach, separated by commas" required><?php echo htmlspecialchars($user['subjects'] ?? ''); ?></textarea>
        </div>
      <?php endif; ?>
      <button type="submit" class="btn-primary">Save Changes</button>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('cropperModal');
  const cropperImage = document.getElementById('cropperImage');
  const profilePhotoInput = document.getElementById('profile_photo');
  const croppedImageInput = document.getElementById('cropped_image');
  const closeBtn = document.querySelector('.close');
  const cancelBtn = document.getElementById('cancelCrop');
  const cropBtn = document.getElementById('cropImage');
  let cropper;

  // Open modal when file is selected
  profilePhotoInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        cropperImage.src = e.target.result;
        modal.style.display = 'block';
        if (cropper) {
          cropper.destroy();
        }
        cropper = new Cropper(cropperImage, {
          aspectRatio: 1,
          viewMode: 1,
          dragMode: 'move',
          autoCropArea: 0.8,
          restore: false,
          guides: true,
          center: true,
          highlight: false,
          cropBoxMovable: true,
          cropBoxResizable: true,
          toggleDragModeOnDblclick: false,
        });
      };
      reader.readAsDataURL(file);
    }
  });

  // Close modal
  function closeModal() {
    modal.style.display = 'none';
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
  }

  closeBtn.onclick = closeModal;
  cancelBtn.onclick = closeModal;

  // Crop and save
  cropBtn.addEventListener('click', function() {
    if (!cropper) return;

    const canvas = cropper.getCroppedCanvas({
      width: 300,
      height: 300,
      fillColor: '#fff',
      imageSmoothingEnabled: true,
      imageSmoothingQuality: 'high',
    });

    if (canvas) {
      const croppedImageData = canvas.toDataURL('image/jpeg', 0.9);
      croppedImageInput.value = croppedImageData;
      closeModal();
    }
  });

  // Close modal when clicking outside
  window.onclick = function(event) {
    if (event.target == modal) {
      closeModal();
    }
  };
});
</script>
<?php
$content = ob_get_clean();
require '../src/Includes/layout.php';
?> 