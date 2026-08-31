-- Manutenzione: individua e rimuove misurazioni corporee duplicate.
-- Duplicato = stesso utente, stesso giorno e stessi valori di composizione corporea.
-- Cambia @user_id se devi operare su un utente diverso.

SET @user_id := 1;

-- 1) Preview: gruppi duplicati. Esegui prima solo questa SELECT se vuoi controllare.
SELECT
  DATE(measured_at) AS measured_date,
  weight_kg,
  bmi,
  body_fat,
  body_water,
  muscle,
  bone,
  left_arm_body_fat,
  left_arm_muscle,
  right_arm_body_fat,
  right_arm_muscle,
  left_leg_body_fat,
  left_leg_muscle,
  right_leg_body_fat,
  right_leg_muscle,
  trunk_body_fat,
  trunk_muscle,
  metabolic_age,
  heart_rate_bpm,
  visceral_fat,
  COUNT(*) AS duplicate_count,
  GROUP_CONCAT(id ORDER BY measured_at, id) AS ids
FROM body_measurements
WHERE user_id = @user_id
GROUP BY
  user_id,
  DATE(measured_at),
  weight_kg,
  bmi,
  body_fat,
  body_water,
  muscle,
  bone,
  left_arm_body_fat,
  left_arm_muscle,
  right_arm_body_fat,
  right_arm_muscle,
  left_leg_body_fat,
  left_leg_muscle,
  right_leg_body_fat,
  right_leg_muscle,
  trunk_body_fat,
  trunk_muscle,
  metabolic_age,
  heart_rate_bpm,
  visceral_fat
HAVING COUNT(*) > 1
ORDER BY measured_date;

-- 2) Pulizia: lascia la prima riga del gruppo e cancella le copie successive.
START TRANSACTION;

CREATE TEMPORARY TABLE duplicate_body_measurement_ids AS
SELECT id
FROM (
  SELECT
    id,
    ROW_NUMBER() OVER (
      PARTITION BY
        user_id,
        DATE(measured_at),
        weight_kg,
        bmi,
        body_fat,
        body_water,
        muscle,
        bone,
        left_arm_body_fat,
        left_arm_muscle,
        right_arm_body_fat,
        right_arm_muscle,
        left_leg_body_fat,
        left_leg_muscle,
        right_leg_body_fat,
        right_leg_muscle,
        trunk_body_fat,
        trunk_muscle,
        metabolic_age,
        heart_rate_bpm,
        visceral_fat
      ORDER BY measured_at, id
    ) AS duplicate_rank
  FROM body_measurements
  WHERE user_id = @user_id
) ranked
WHERE duplicate_rank > 1;

SELECT COUNT(*) AS rows_to_delete FROM duplicate_body_measurement_ids;

DELETE bm
FROM body_measurements bm
JOIN duplicate_body_measurement_ids d ON d.id = bm.id;

COMMIT;

-- 3) Verifica: deve tornare 0.
SELECT COUNT(*) AS remaining_duplicate_groups
FROM (
  SELECT 1
  FROM body_measurements
  WHERE user_id = @user_id
  GROUP BY
    user_id,
    DATE(measured_at),
    weight_kg,
    bmi,
    body_fat,
    body_water,
    muscle,
    bone,
    left_arm_body_fat,
    left_arm_muscle,
    right_arm_body_fat,
    right_arm_muscle,
    left_leg_body_fat,
    left_leg_muscle,
    right_leg_body_fat,
    right_leg_muscle,
    trunk_body_fat,
    trunk_muscle,
    metabolic_age,
    heart_rate_bpm,
    visceral_fat
  HAVING COUNT(*) > 1
) duplicate_groups;
