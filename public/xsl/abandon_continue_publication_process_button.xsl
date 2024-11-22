<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:exsl="http://exslt.org/common"
                xmlns:php="http://php.net/xsl"
                version="1.0"
                extension-element-prefixes="exsl"
                xmlns="http://www.openarchives.org/OAI/2.0/">

    <xsl:output method="html" encoding="utf-8" indent="yes"/>

    <xsl:template match="/record">
        <xsl:variable name="prefixUrl" select="episciences/prefixUrl/text()"/>
        <xsl:if test="episciences/canAbandonContinuePublicationProcess">
            <xsl:variable name="fontType">
                <xsl:choose>
                    <xsl:when test="episciences/isAllowedToContinuePublicationProcess">
                        <xsl:value-of select="'fa-exclamation-circle'"/>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:value-of select="'fa-stop-circle'"/>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:variable>
            <xsl:variable name="buttonColor">
                <xsl:choose>
                    <xsl:when test="episciences/isAllowedToContinuePublicationProcess">
                        <xsl:value-of select="'btn-default'"/><!-- continue publication process -->
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:value-of select="'btn-default'"/><!-- stop publication process -->
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:variable>
            <xsl:variable name="buttonText">
                <xsl:choose>
                    <xsl:when test="episciences/isAllowedToContinuePublicationProcess">
                        <xsl:value-of select="'Reprendre le processus de publication'"/>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:value-of select="'Abandonner le processus de publication'"/>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:variable>
            <xsl:variable name="action">
                <xsl:choose>
                    <xsl:when test="episciences/isAllowedToContinuePublicationProcess">
                        <xsl:value-of select="'continue'"/>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:value-of select="'abandon'"/>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:variable>

            <xsl:if test="episciences/status != 17 or (episciences/isAllowedToContinuePublicationProcess)">

                <div id="abandonContinuePublicationProcess" class="pull-right" style="margin-top: 5px;">

                        <a class="modal-opener" data-callback="submit" data-width="25%">
                            <xsl:attribute name="title">
                                <xsl:value-of select="php:function('Ccsd_Tools::translate', $buttonText)"/>
                            </xsl:attribute>
                            <xsl:attribute name="href">
                                <xsl:value-of
                                        select="concat(concat(concat($prefixUrl, 'paper/'), $action), 'publicationprocess/docid/', episciences/id)"/>
                            </xsl:attribute>

                            <button id="pause-play-process-button" class="btn {$buttonColor} btn-xs">
                                <i class="fas {$fontType}" style="margin-right: 5px"/>
                                <xsl:value-of select="php:function('Ccsd_Tools::translate', $buttonText)"/>
                            </button>

                        </a>

                </div>
            </xsl:if>
        </xsl:if>

    </xsl:template>

</xsl:stylesheet>