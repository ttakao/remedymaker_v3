システム仕様
ログイン処理(A)
A-1. ユーザーは別途、登録したメールアドレスでログインする。
ユーザーの属性は以下のとおり。mysqlデータベースにusersというテーブルで登録される。

ID　unsigned_int 会員ID
regist_date datetime 登録日
update_date datetime 更新日
name varchar 255 名前
mail varchar 255 メールアドレス
learning varchar 255 研修参加履歴
paid bool 1 0/1 使用料の支払いの有無(1=支払いあり）
challenge varchar 255 ランダムパスワード
memo text 備考欄

A-2. システムはメールアドレスに６桁の英数字のランダムな文字列を送り、同時にusersテーブルのchallengeにMD5で暗号化して書き込む
（update_dateも更新）

A-3. システムはメールアドレスと、パスキー入力のフォームを表示すｓる

A-4. ユーザーはメールを確認し、パスキー文字列を入力する。

A-5. システムはusersテーブルのupdaate_dateと現在時間を比べ、5分以内ならば、入力されたパスキーをMD5に暗号化し、challengeに登録されている値と比較する。同じならば正しいので、測定システム（B)を呼び出す。正しくない場合、最初の画面に戻る

データ検索処理(B)
B-1. データは以下のテーブルに入っている



B-2. システムは大カテゴリー

B-3. 

データ転送処理(C)
