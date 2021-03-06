<?php

namespace app\models;

use Yii;
use nineinchnick\usr\components;

/**
 * This is the model class for table "{{users}}".
 *
 * @property integer $id
 * @property string $username
 * @property string $password
 * @property string $email
 * @property string $firstname
 * @property string $lastname
 * @property string $activation_key
 * @property datetime $created_on
 * @property datetime $updated_on
 * @property datetime $last_visit_on
 * @property datetime $password_set_on
 * @property boolean $email_verified
 * @property boolean $is_active
 * @property boolean $is_disabled
 */
class User extends \yii\db\ActiveRecord
    implements
    components\IdentityInterface,
    components\PasswordHistoryIdentityInterface,
    components\ActivatedIdentityInterface,
    components\EditableIdentityInterface,
    components\ManagedIdentityInterface
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%users}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        // password is unsafe on purpose, assign it manually after hashing only if not empty
        return [
            [['username', 'email', 'firstname', 'lastname'], 'trim'],
            [['auth_key', 'activation_key', 'access_token', 'created_on', 'updated_on', 'last_visit_on', 'password_set_on', 'email_verified'], 'trim', 'on' => 'search'],
            [['username', 'email', 'firstname', 'lastname', 'is_active', 'is_disabled'], 'default'],
            [['auth_key', 'activation_key', 'access_token', 'created_on', 'updated_on', 'last_visit_on', 'password_set_on', 'email_verified'], 'default', 'on' => 'search'],
            [['username', 'email', 'is_active', 'is_disabled', 'email_verified'], 'required', 'except' => 'search'],
            [['created_on', 'updated_on', 'last_visit_on', 'password_set_on'], 'date', 'format' => ['yyyy-MM-dd', 'yyyy-MM-dd HH:mm', 'yyyy-MM-dd HH:mm:ss'], 'on' => 'search'],
            [['auth_key', 'activation_key', 'access_token'], 'string', 'max' => 128, 'on' => 'search'],
            [['is_active', 'is_disabled', 'email_verified'], 'boolean'],
            [['username', 'email'], 'unique', 'except' => 'search'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('models', 'ID'),
            'username' => Yii::t('models', 'Username'),
            'password' => Yii::t('models', 'Password'),
            'email' => Yii::t('models', 'Email'),
            'firstname' => Yii::t('models', 'Firstname'),
            'lastname' => Yii::t('models', 'Lastname'),
            'auth_key' => Yii::t('models', 'Auth Key'),
            'activation_key' => Yii::t('models', 'Activation Key'),
            'access_token' => Yii::t('models', 'Access Token'),
            'created_on' => Yii::t('models', 'Created On'),
            'updated_on' => Yii::t('models', 'Updated On'),
            'last_visit_on' => Yii::t('models', 'Last Visit On'),
            'password_set_on' => Yii::t('models', 'Password Set On'),
            'email_verified' => Yii::t('models', 'Email Verified'),
            'is_active' => Yii::t('models', 'Is Active'),
            'is_disabled' => Yii::t('models', 'Is Disabled'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_on = date('Y-m-d H:i:s');
        } else {
            $this->updated_on = date('Y-m-d H:i:s');
        }

        if (parent::beforeSave($insert)) {
            if ($this->isNewRecord) {
                $this->auth_key = Yii::$app->getSecurity()->generateRandomString();
            }

            return true;
        }

        return false;
    }

    /**
     * Finds an identity by the given username.
     *
     * @param  string                 $username the username to be looked for
     * @return IdentityInterface|null the identity object that matches the given ID.
     */
    public static function findByUsername($username)
    {
        return self::findOne(['username' => $username]);
    }

    /**
     * @param  string $password password to validate
     * @return bool   if password provided is valid for current user
     */
    public function verifyPassword($password)
    {
        try {
            return Yii::$app->security->validatePassword($password, $this->password);
        } catch (\yii\base\InvalidParamException $e) {
            return false;
        }
    }

    // {{{ IdentityInterface

    /**
     * Finds an identity by the given ID.
     *
     * @param  string|integer         $id the ID to be looked for
     * @return IdentityInterface|null the identity object that matches the given ID.
     */
    public static function findIdentity($id)
    {
        return self::findOne(['id' => $id]);
    }

    /**
     * Finds an identity by the given secrete token.
     *
     * @param  string                $token the secrete token
     * @param  mixed                 $type  the type of the token. The value of this parameter depends on the implementation.
     * @return IdentityInterface     the identity object that matches the given token.
     * @throws NotSupportedException
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['access_token' => $token]);
    }

    /**
     * @return int|string current user ID
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string current user auth key
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @param  string  $authKey
     * @return boolean if auth key is valid for current user
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * @inheritdoc
     */
    public function authenticate($password)
    {
        if (!$this->is_active) {
            return [self::ERROR_INACTIVE, Yii::t('usr', 'User account has not been activated yet.')];
        }
        if ($this->is_disabled) {
            return [self::ERROR_DISABLED, Yii::t('usr', 'User account has been disabled.')];
        }
        if (!$this->verifyPassword($password)) {
            return [self::ERROR_INVALID, Yii::t('usr', 'Invalid username or password.')];
        }

        $this->last_visit_on = date('Y-m-d H:i:s');
        $this->save(false);

        return true;
    }

    // }}}

    // {{{ PasswordHistoryIdentityInterface

    /**
     * Returns the date when specified password was last set or null if it was never used before.
     * If null is passed, returns date of setting current password.
     * @param  string $password new password or null if checking when the current password has been set
     * @return string date in YYYY-MM-DD format or null if password was never used.
     */
    public function getPasswordDate($password = null)
    {
        if ($password === null) {
            return $this->password_set_on;
        }

        return null;
    }

    /**
     * Changes the password and updates last password change date.
     * Saves old password so it couldn't be used again.
     * @param  string  $password new password
     * @return boolean
     */
    public function resetPassword($password)
    {
        $hashedPassword = Yii::$app->security->generatePasswordHash($password);
        $this->setAttributes([
            'password' => $hashedPassword,
            'password_set_on' => date('Y-m-d H:i:s'),
        ], false);

        return $usedPassword->save() && $this->save();
    }

    // }}}

    // {{{ EditableIdentityInterface

    /**
     * Maps the \nineinchnick\usr\models\ProfileForm attributes to the identity attributes
     * @see \nineinchnick\usr\models\ProfileForm::attributes()
     * @return array
     */
    public function identityAttributesMap()
    {
        // notice the capital N in name
        return ['username' => 'username', 'email' => 'email', 'firstName' => 'firstname', 'lastName' => 'lastname'];
    }

    /**
     * Saves a new or existing identity. Does not set or change the password.
     * @see PasswordHistoryIdentityInterface::resetPassword()
     * Should detect if the email changed and mark it as not verified.
     * @param  boolean $requireVerifiedEmail
     * @return boolean
     */
    public function saveIdentity($requireVerifiedEmail = false)
    {
        if ($this->isNewRecord) {
            $this->password = 'x';
            $this->is_active = $requireVerifiedEmail ? 0 : 1;
            $this->is_disabled = 0;
            $this->email_verified = 0;
        }
        if (!$this->save()) {
            Yii::warning('Failed to save user: '.print_r($this->getErrors(), true), 'usr');

            return false;
        }

        return true;
    }

    /**
     * Sets attributes like username, email, first and last name.
     * Password should be changed using only the resetPassword() method from the PasswordHistoryIdentityInterface.
     * @param  array   $attributes
     * @return boolean
     */
    public function setIdentityAttributes(array $attributes)
    {
        $allowedAttributes = $this->identityAttributesMap();
        foreach ($attributes as $name => $value) {
            if (isset($allowedAttributes[$name])) {
                $key = $allowedAttributes[$name];
                $this->$key = $value;
            }
        }

        return true;
    }

    /**
     * Returns attributes like username, email, first and last name.
     * @return array
     */
    public function getIdentityAttributes()
    {
        $allowedAttributes = array_flip($this->identityAttributesMap());
        $result = [];
        foreach ($this->getAttributes() as $name => $value) {
            if (isset($allowedAttributes[$name])) {
                $result[$allowedAttributes[$name]] = $value;
            }
        }

        return $result;
    }

    // }}}

    // {{{ ActivatedIdentityInterface

    /**
     * Checks if user account is active. This should not include disabled (banned) status.
     * This could include if the email address has been verified.
     * Same checks should be done in the authenticate() method, because this method is not called before logging in.
     * @return boolean
     */
    public function isActive()
    {
        return (bool) $this->is_active;
    }

    /**
     * Checks if user account is disabled (banned). This should not include active status.
     * @return boolean
     */
    public function isDisabled()
    {
        return (bool) $this->is_disabled;
    }

    /**
     * Checks if user email address is verified.
     * @return boolean
     */
    public function isVerified()
    {
        return (bool) $this->email_verified;
    }

    /**
     * Generates and saves a new activation key used for verifying email and restoring lost password.
     * The activation key is then sent by email to the user.
     *
     * Note: only the last generated activation key should be valid and an activation key
     * should have it's generation date saved to verify it's age later.
     *
     * @return string
     */
    public function getActivationKey()
    {
        $this->activation_key = Yii::$app->security->generateRandomKey();

        return $this->save(false) ? $this->activation_key : false;
    }

    /**
     * Verifies if specified activation key matches the saved one and if it's not too old.
     * This method should not alter any saved data.
     * @param  string  $activationKey
     * @return integer the verification error code. If there is an error, the error code will be non-zero.
     */
    public function verifyActivationKey($activationKey)
    {
        return $this->activation_key === $activationKey ? self::ERROR_AKEY_NONE : self::ERROR_AKEY_INVALID;
    }

    /**
     * Verify users email address, which could also activate his account and allow him to log in.
     * Call only after verifying the activation key.
     * @param  boolean $requireVerifiedEmail
     * @return boolean
     */
    public function verifyEmail($requireVerifiedEmail = false)
    {
        if ($this->email_verified) {
            return true;
        }
        $this->email_verified = 1;
        if ($requireVerifiedEmail && !$this->is_active) {
            $this->is_active = 1;
        }

        return $this->save(false);
    }

    /**
     * Returns user email address.
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    // }}}

    // {{{ ManagedIdentityInterface

    /**
     * @inheritdoc
     */
    public function getDataProvider(\nineinchnick\usr\models\SearchForm $searchForm)
    {
        $query = self::find();

        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => $query,
        ]);

        $query->andFilterWhere([
            'id'             => $searchForm->id,
            'created_on'     => $searchForm->createdOn,
            'updated_on'     => $searchForm->updatedOn,
            'last_visit_on'  => $searchForm->lastVisitOn,
            'email_verified' => $searchForm->emailVerified,
            'is_active'      => $searchForm->isActive,
            'is_disabled'    => $searchForm->isDisabled,
        ]);

        //! @todo add lowercase filter
        $query->andFilterWhere(['like', 'username', $searchForm->username])
            ->andFilterWhere(['like', 'firstname', $searchForm->firstName])
            ->andFilterWhere(['like', 'lastname', $searchForm->lastName])
            ->andFilterWhere(['like', 'email', $searchForm->email]);

        return $dataProvider;
    }

    /**
     * @inheritdoc
     */
    public function toggleStatus($status)
    {
        switch ($status) {
        case self::STATUS_EMAIL_VERIFIED: $this->email_verified = !$this->email_verified; break;
        case self::STATUS_IS_ACTIVE: $this->is_active = !$this->is_active; break;
        case self::STATUS_IS_DISABLED: $this->is_disabled = !$this->is_disabled; break;
        }

        return $this->save(false);
    }

    /**
     * @inheritdoc
     */
    public function getTimestamps($key = null)
    {
        $timestamps = [
            'createdOn' => $this->created_on,
            'updatedOn' => $this->updated_on,
            'lastVisitOn' => $this->last_visit_on,
            'passwordSetOn' => $this->password_set_on,
        ];
        // can't use isset, since it returns false for null values
        return $key === null || !array_key_exists($key, $timestamps) ? $timestamps : $timestamps[$key];
    }

    // }}}
}
